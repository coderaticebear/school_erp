<?php

namespace App\Services;

use App\Models\Login;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

/**
 * Two-factor sign-in with an authenticator app (Security SEC-06): secrets, QR codes, code checks and recovery codes.
 */
class TwoFactorAuth
{
    /**
     * Letters and digits for recovery codes. Leaves out 0, O, 1, I and L, which are easy to misread.
     */
    private const RECOVERY_ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    /**
     * Characters in each half of a recovery code (XXXXX-XXXXX).
     */
    private const RECOVERY_HALF_LENGTH = 5;

    /**
     * Size of the QR code in pixels.
     */
    private const QR_SIZE = 192;

    public function __construct(private Google2FA $google2fa) {}

    /**
     * True when users must set up two-factor. Production always requires it, whatever the switch says.
     */
    public function required(): bool
    {
        return (bool) config('two-factor.enabled') || app()->isProduction();
    }

    /**
     * A new random secret (32 base32 characters) for an authenticator app.
     */
    public function makeSecret(): string
    {
        return $this->google2fa->generateSecretKey(32);
    }

    /**
     * The otpauth:// link that the QR code holds: the school name is the issuer and the email the account.
     */
    public function otpauthUrl(Login $login, string $secret): string
    {
        return $this->google2fa->getQRCodeUrl((string) config('school.name'), (string) $login->email, $secret);
    }

    /**
     * The QR code as inline SVG, made on our server so no outside server sees the secret.
     */
    public function qrSvg(string $otpauthUrl): string
    {
        $writer = new Writer(new ImageRenderer(new RendererStyle(self::QR_SIZE), new SvgImageBackEnd));

        return trim(preg_replace('/^<\?xml[^>]*\?>/', '', $writer->writeString($otpauthUrl)));
    }

    /**
     * Check a 6-digit code from the authenticator app. A code is accepted from one 30-second step before or after
     * now, and only once: the accepted time step is saved, and a code from that step or an older one fails.
     */
    public function verifyCode(Login $login, string $code): bool
    {
        $code = str_replace(' ', '', $code);
        $secret = $login->two_factor_secret;

        if ($secret === null || preg_match('/^\d{6}$/', $code) !== 1) {
            return false;
        }

        $timestep = $this->google2fa->verifyKeyNewer($secret, $code, $login->two_factor_last_timestep ?? 0, 1);

        if (! is_int($timestep)) {
            return false;
        }

        $updated = Login::whereKey($login->id)
            ->where(fn (Builder $query) => $query
                ->whereNull('two_factor_last_timestep')
                ->orWhere('two_factor_last_timestep', '<', $timestep))
            ->update(['two_factor_last_timestep' => $timestep]);

        if ($updated !== 1) {
            return false;
        }

        $login->forceFill(['two_factor_last_timestep' => $timestep])->syncOriginalAttribute('two_factor_last_timestep');

        return true;
    }

    /**
     * The login's secret that is not confirmed yet. Makes and saves a new one when the login has none, so a reload
     * of the set-up page shows the same QR code. The secret stays in the login row, never in the session.
     */
    public function pendingSecret(Login $login): string
    {
        if ($login->two_factor_secret === null) {
            $login->forceFill(['two_factor_secret' => $this->makeSecret()])->save();
        }

        return $login->two_factor_secret;
    }

    /**
     * Turn on two-factor after the first correct code, and make the first set of recovery codes. The login row is
     * locked, so when two set-up requests come at the same time, only the first one turns on two-factor and makes
     * codes. The second one gets null and does not replace the first codes.
     *
     * @return list<string>|null the recovery codes in clear text, to show to the user this one time only, or null
     *                           when two-factor is already on
     */
    public function enable(Login $login): ?array
    {
        return DB::transaction(function () use ($login): ?array {
            $locked = Login::whereKey($login->id)->lockForUpdate()->firstOrFail();

            if ($locked->hasTwoFactorEnabled()) {
                return null;
            }

            $login->forceFill(['two_factor_confirmed_at' => now()])->save();

            return $this->generateRecoveryCodes($login);
        });
    }

    /**
     * Turn off two-factor for a login that lost its device (FR-2FA-12, 13, 16). Clears the secret, the recovery
     * codes and the replay marker, changes the Remember Me token, and deletes every stored session of the login,
     * so the user must sign in again and set up two-factor at the next sign-in.
     */
    public function reset(Login $login): void
    {
        DB::transaction(function () use ($login): void {
            $login->forceFill([
                'two_factor_secret' => null,
                'two_factor_confirmed_at' => null,
                'two_factor_recovery_codes' => null,
                'two_factor_last_timestep' => null,
            ])->setRememberToken(Str::random(60));

            $login->save();

            DB::connection(config('session.connection'))
                ->table((string) config('session.table'))
                ->where('user_id', $login->getKey())
                ->delete();
        });
    }

    /**
     * Make a new set of recovery codes and save only their hashes. The old codes stop working.
     *
     * @return list<string> the codes in clear text, to show to the user this one time only
     */
    public function generateRecoveryCodes(Login $login): array
    {
        $codes = [];

        for ($i = 0; $i < (int) config('two-factor.recovery_code_count'); $i++) {
            $codes[] = $this->randomRecoveryCode();
        }

        $login->forceFill([
            'two_factor_recovery_codes' => array_map($this->hashRecoveryCode(...), $codes),
        ])->save();

        return $codes;
    }

    /**
     * Use a recovery code. It works once: a matching code is removed. The login row is locked, so two requests
     * at the same time cannot both use one code.
     */
    public function useRecoveryCode(Login $login, string $code): bool
    {
        $normalised = $this->normaliseRecoveryCode($code);

        if ($normalised === null) {
            return false;
        }

        $hash = $this->hashRecoveryCode($normalised);

        return DB::transaction(function () use ($login, $hash): bool {
            $locked = Login::whereKey($login->id)->lockForUpdate()->firstOrFail();
            $hashes = $locked->two_factor_recovery_codes ?? [];

            foreach ($hashes as $index => $storedHash) {
                if (hash_equals((string) $storedHash, $hash)) {
                    unset($hashes[$index]);
                    $locked->forceFill(['two_factor_recovery_codes' => array_values($hashes)])->save();

                    $login->forceFill(['two_factor_recovery_codes' => array_values($hashes)])
                        ->syncOriginalAttribute('two_factor_recovery_codes');

                    return true;
                }
            }

            return false;
        });
    }

    /**
     * How many unused recovery codes the login has.
     */
    public function remainingRecoveryCodes(Login $login): int
    {
        return count($login->two_factor_recovery_codes ?? []);
    }

    /**
     * True when the login has two-factor on and fewer unused recovery codes than the warning count (FR-2FA-22).
     */
    public function needsNewRecoveryCodes(Login $login): bool
    {
        return $login->hasTwoFactorEnabled()
            && $this->remainingRecoveryCodes($login) < (int) config('two-factor.low_codes_warning');
    }

    /**
     * One random code in the form XXXXX-XXXXX.
     */
    private function randomRecoveryCode(): string
    {
        $characters = '';

        for ($i = 0; $i < self::RECOVERY_HALF_LENGTH * 2; $i++) {
            $characters .= self::RECOVERY_ALPHABET[random_int(0, strlen(self::RECOVERY_ALPHABET) - 1)];
        }

        return $this->formatRecoveryCode($characters);
    }

    /**
     * The code as the generator formats it, whatever the case, spaces or dashes the user typed.
     * Null when it cannot be a recovery code.
     */
    private function normaliseRecoveryCode(string $code): ?string
    {
        $characters = strtoupper(str_replace([' ', '-'], '', $code));

        if (strlen($characters) !== self::RECOVERY_HALF_LENGTH * 2) {
            return null;
        }

        return $this->formatRecoveryCode($characters);
    }

    /**
     * Put the dash between the two halves.
     */
    private function formatRecoveryCode(string $characters): string
    {
        return substr($characters, 0, self::RECOVERY_HALF_LENGTH).'-'.substr($characters, self::RECOVERY_HALF_LENGTH);
    }

    /**
     * The stored form of a recovery code. The clear code is never stored or logged.
     */
    private function hashRecoveryCode(string $code): string
    {
        return hash_hmac('sha256', $code, (string) config('app.key'));
    }
}
