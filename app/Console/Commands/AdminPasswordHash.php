<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

#[Signature('app:admin-password-hash')]
#[Description('Generate a password hash for the Vercel dashboard administrator without a database')]
class AdminPasswordHash extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $password = $this->secret('Administrator password (at least 12 characters)');

        if (! is_string($password) || mb_strlen($password) < 12) {
            $this->error('Use a password of at least 12 characters.');

            return self::FAILURE;
        }

        $this->line(Hash::make($password));

        return self::SUCCESS;
    }
}
