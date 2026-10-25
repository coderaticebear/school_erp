<?php

namespace App\Http\Controllers;

use App\Http\Middleware\EnsureTwoFactor;
use App\Http\Requests\EnableTwoFactorRequest;
use App\Http\Requests\VerifyTwoFactorRequest;
use App\Models\Login;
use App\Services\TwoFactorAuth;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Two-factor sign-in pages (Security SEC-06): set-up, the code challenge and the Security page.
 */
class TwoFactorController extends Controller
{
    /**
     * Flash key for the new recovery codes in clear text. They are shown once, on the next page only.
     */
    public const FLASH_RECOVERY_CODES = 'two_factor_new_recovery_codes';

    /**
     * The two-factor set-up page: the QR code and the secret for the authenticator app.
     */
    public function setup(Request $request, TwoFactorAuth $twoFactor): View|RedirectResponse
    {
        /** @var \App\Models\Login $login */
        $login = $request->user();

        if ($login->hasTwoFactorEnabled()) {
            return redirect()->route('dashboard');
        }

        $secret = $twoFactor->pendingSecret($login);

        return view('two-factor.setup', [
            'secret' => $secret,
            'qrSvg' => $twoFactor->qrSvg($twoFactor->otpauthUrl($login, $secret)),
        ]);
    }

    /**
     * Turn on two-factor when the first code from the app is correct, then show the recovery codes.
     *
     * @throws ValidationException when the code is not correct
     */
    public function enable(EnableTwoFactorRequest $request, TwoFactorAuth $twoFactor): RedirectResponse
    {
        /** @var \App\Models\Login $login */
        $login = $request->user();

        if ($login->hasTwoFactorEnabled()) {
            return redirect()->route('dashboard');
        }

        if ($login->two_factor_secret === null) {
            return redirect()->route('two-factor.setup');
        }

        if (! $twoFactor->verifyCode($login, $request->string('code')->toString())) {
            throw ValidationException::withMessages([
                'code' => 'That code is not correct. Check the code in your app and try again.',
            ]);
        }

        $codes = $twoFactor->enable($login);

        if ($codes === null) {
            return redirect()->route('dashboard');
        }

        $request->session()->put(EnsureTwoFactor::SESSION_VERIFIED, $login->id);
        $request->session()->forget(EnsureTwoFactor::SESSION_STARTED);
        $request->session()->regenerate();

        return redirect()->route('two-factor.codes')->with(self::FLASH_RECOVERY_CODES, $codes);
    }

    /**
     * The new recovery codes, shown once. A reload goes to the dashboard.
     */
    public function codes(Request $request): View|RedirectResponse
    {
        $codes = $request->session()->get(self::FLASH_RECOVERY_CODES);

        if (! is_array($codes) || $codes === []) {
            return redirect()->route('dashboard');
        }

        return view('two-factor.codes', ['codes' => $codes]);
    }

    /**
     * The page that asks for the code from the authenticator app, or a recovery code.
     */
    public function challenge(Request $request): View|RedirectResponse
    {
        return $this->redirectIfNotChallenged($request) ?? view('two-factor.challenge');
    }

    /**
     * Check the code or the recovery code. A correct one marks the session as verified and opens the page the user
     * asked for. Wrong, used and replayed codes all get the same reply.
     *
     * @throws ValidationException when the code is not correct
     */
    public function verify(VerifyTwoFactorRequest $request, TwoFactorAuth $twoFactor): RedirectResponse
    {
        $redirect = $this->redirectIfNotChallenged($request) ?? $this->endChallengeIfTooLate($request);

        if ($redirect !== null) {
            return $redirect;
        }

        /** @var Login $login */
        $login = $request->user();
        $usesRecoveryCode = ! $request->filled('code');

        $verified = $usesRecoveryCode
            ? $twoFactor->useRecoveryCode($login, $request->string('recovery_code')->toString())
            : $twoFactor->verifyCode($login, $request->string('code')->toString());

        if (! $verified) {
            throw ValidationException::withMessages([
                ($usesRecoveryCode ? 'recovery_code' : 'code') => 'That code is not correct. Try again.',
            ]);
        }

        $request->session()->put(EnsureTwoFactor::SESSION_VERIFIED, $login->id);
        $request->session()->forget(EnsureTwoFactor::SESSION_STARTED);
        $request->session()->regenerate();

        $response = redirect()->to($this->pageAfterChallenge($request, $login));

        return $usesRecoveryCode
            ? $response->with('success', $this->recoveryCodesLeftMessage($twoFactor->remainingRecoveryCodes($login)))
            : $response;
    }

    /**
     * The Security page: the two-factor status, the count of unused recovery codes, and new codes once after they
     * are made.
     */
    public function manage(Request $request, TwoFactorAuth $twoFactor): View
    {
        /** @var Login $login */
        $login = $request->user();
        $newCodes = $request->session()->get(self::FLASH_RECOVERY_CODES);

        return view('two-factor.manage', [
            'login' => $login,
            'remaining' => $twoFactor->remainingRecoveryCodes($login),
            'newCodes' => is_array($newCodes) ? $newCodes : [],
        ]);
    }

    /**
     * Make a new set of recovery codes (FR-2FA-11). The route needs a recent password. The old codes stop working.
     */
    public function regenerateRecoveryCodes(Request $request, TwoFactorAuth $twoFactor): RedirectResponse
    {
        /** @var Login $login */
        $login = $request->user();

        if (! $login->hasTwoFactorEnabled()) {
            return redirect()->route('two-factor.setup');
        }

        return redirect()->route('security.index')
            ->with(self::FLASH_RECOVERY_CODES, $twoFactor->generateRecoveryCodes($login));
    }

    /**
     * Where the user goes after the challenge: the page they first asked for, or else their own dashboard. The
     * generic /dashboard redirects again, and that second redirect would use up the flash message.
     */
    private function pageAfterChallenge(Request $request, Login $login): string
    {
        $intended = $request->session()->pull('url.intended');
        $dashboardRoute = $login->dashboardRouteName();

        if (is_string($intended) && $intended !== '' && $intended !== route('dashboard')) {
            return $intended;
        }

        return route($dashboardRoute ?? 'dashboard');
    }

    /**
     * Users with no two-factor go to set-up, and a session that is already verified goes to the dashboard.
     */
    private function redirectIfNotChallenged(Request $request): ?RedirectResponse
    {
        /** @var Login $login */
        $login = $request->user();

        if (! $login->hasTwoFactorEnabled()) {
            return redirect()->route('two-factor.setup');
        }

        $verifiedId = $request->session()->get(EnsureTwoFactor::SESSION_VERIFIED);

        if (is_numeric($verifiedId) && (int) $verifiedId === (int) $login->id) {
            return redirect()->route('dashboard');
        }

        return null;
    }

    /**
     * Security SEC-06 (FR-2FA-09): the code must come within the time limit after the password. Later, the user is
     * logged out and must sign in again.
     */
    private function endChallengeIfTooLate(Request $request): ?RedirectResponse
    {
        if (! $request->session()->has(EnsureTwoFactor::SESSION_STARTED)) {
            $request->session()->put(EnsureTwoFactor::SESSION_STARTED, now()->timestamp);
        }

        $elapsedSeconds = now()->timestamp - (int) $request->session()->get(EnsureTwoFactor::SESSION_STARTED);

        if ($elapsedSeconds <= (int) config('two-factor.challenge_minutes') * 60) {
            return null;
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->withErrors(['email' => 'Your sign-in took too long. Please sign in again.']);
    }

    /**
     * The message after a sign-in with a recovery code: how many codes are left, and a reminder when few are left.
     */
    private function recoveryCodesLeftMessage(int $remaining): string
    {
        $message = "You signed in with a recovery code. You have {$remaining} recovery ".Str::plural('code', $remaining).' left.';

        return $remaining < (int) config('two-factor.low_codes_warning')
            ? $message.' Ask for new codes soon.'
            : $message;
    }
}
