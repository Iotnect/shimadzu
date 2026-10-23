<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:create-admin {username} {--password= : The initial password; otherwise prompt securely}')]
#[Description('Create or update the local dashboard administrator')]
class CreateAdmin extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $username = mb_strtolower((string) $this->argument('username'));
        $password = $this->option('password') ?? $this->secret('Password');

        if (! preg_match('/^[a-z0-9._-]{1,80}$/', $username) || ! is_string($password) || mb_strlen($password) < 8) {
            $this->error('Use a valid username and a password of at least 8 characters.');

            return self::FAILURE;
        }

        User::query()->updateOrCreate(['username' => $username], [
            'name' => 'Administrator',
            'email' => $username.'@localhost',
            'password' => $password,
        ]);

        $this->info('Dashboard administrator configured.');

        return self::SUCCESS;
    }
}
