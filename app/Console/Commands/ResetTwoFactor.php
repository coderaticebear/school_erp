<?php

namespace App\Console\Commands;

use App\Models\Login;
use App\Services\TwoFactorAuth;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Reset the two-factor sign-in of any account by email (SEC-06, FR-2FA-13). This is the only way to reset an admin
 * who lost the device and has no recovery code. It prints no secret.
 */
class ResetTwoFactor extends Command
{
    /**
     * @var string
     */
    protected $signature = 'two-factor:reset
                            {email? : Email address of the account to reset}';

    /**
     * @var string
     */
    protected $description = 'Turn off two-factor sign-in for an account and end all its sessions';

    public function handle(TwoFactorAuth $twoFactor): int
    {
        $email = trim((string) ($this->argument('email') ?? $this->ask('Email address of the account')));

        $login = $email === '' ? null : Login::whereRaw('lower(email) = ?', [Str::lower($email)])->first();

        if ($login === null) {
            $this->components->error("No account has the email {$email}.");

            return self::FAILURE;
        }

        $twoFactor->reset($login);

        $this->components->info("Two-factor sign-in reset for {$login->email}.");
        $this->line('  All sessions of this account ended. The user sets up two-factor again at the next sign-in.');

        return self::SUCCESS;
    }
}
