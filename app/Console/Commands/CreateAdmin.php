<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:create-admin {email} {--password= : The initial password; otherwise prompt securely}')]
#[Description('Create or update the local dashboard administrator')]
class CreateAdmin extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $email = mb_strtolower(trim((string) $this->argument('email')));
        $password = $this->option('password') ?? $this->secret('Password');

        if (strlen($email) > 254 || ! filter_var($email, FILTER_VALIDATE_EMAIL) || ! is_string($password) || mb_strlen($password) < 12) {
            $this->error('Use a valid email and a password of at least 12 characters.');

            return self::FAILURE;
        }

        User::query()->updateOrCreate(['email' => $email], [
            'name' => 'Administrator',
            'password' => $password,
        ]);

        $this->info('Dashboard administrator configured.');

        return self::SUCCESS;
    }
}
