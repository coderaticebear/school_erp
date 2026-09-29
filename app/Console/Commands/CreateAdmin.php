<?php

namespace App\Console\Commands;

use App\Models\Login;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * The safe way to add an admin, including the first one on a new server (SEC-07): the demo
 * seeders never run in production, and public registration is off.
 */
class CreateAdmin extends Command
{
    /**
     * @var string
     */
    protected $signature = 'create-admin
                            {email? : Email address the new admin signs in with}';

    /**
     * @var string
     */
    protected $description = 'Create an admin account with a generated password, shown once';

    public function handle(): int
    {
        $email = trim((string) ($this->argument('email') ?? $this->ask('Email address for the new admin')));

        $validator = Validator::make(['email' => $email], ['email' => ['required', 'email', 'max:255']]);

        if ($validator->fails()) {
            $this->components->error($validator->errors()->first('email'));

            return self::FAILURE;
        }

        if (Login::whereRaw('lower(email) = ?', [Str::lower($email)])->exists()) {
            $this->components->error("An account with the email {$email} already exists.");

            return self::FAILURE;
        }

        $password = Str::password(24);

        Login::create([
            'email' => $email,
            'password' => Hash::make($password),
            'role' => Login::ROLE_ADMIN,
            'is_active' => true,
        ]);

        $this->components->info("Admin account created for {$email}.");
        $this->line("  Password: {$password}");
        $this->newLine();
        $this->components->warn('The password is shown only this once and is not stored anywhere readable. Pass it on securely.');

        return self::SUCCESS;
    }
}
