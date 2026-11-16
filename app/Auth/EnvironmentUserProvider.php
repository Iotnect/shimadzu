<?php

namespace App\Auth;

use Illuminate\Auth\GenericUser;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Support\Facades\Hash;
use SensitiveParameter;

class EnvironmentUserProvider implements UserProvider
{
    public function __construct(private string $email, #[SensitiveParameter] private string $passwordHash) {}

    public function retrieveById(mixed $identifier): ?Authenticatable
    {
        $user = $this->user();

        return $user !== null && is_string($identifier) && hash_equals($user->getAuthIdentifier(), $identifier) ? $user : null;
    }

    public function retrieveByToken(mixed $identifier, #[SensitiveParameter] mixed $token): ?Authenticatable
    {
        return null;
    }

    /** Configured accounts do not persist remember tokens. */
    public function updateRememberToken(Authenticatable $user, #[SensitiveParameter] mixed $token): void {}

    /** @param array<string, mixed> $credentials */
    public function retrieveByCredentials(#[SensitiveParameter] array $credentials): ?Authenticatable
    {
        $email = $credentials['email'] ?? null;

        return is_string($email) && hash_equals($this->email, mb_strtolower($email)) ? $this->user() : null;
    }

    /** @param array<string, mixed> $credentials */
    public function validateCredentials(Authenticatable $user, #[SensitiveParameter] array $credentials): bool
    {
        $password = $credentials['password'] ?? null;

        return is_string($password) && Hash::check($password, $user->getAuthPassword());
    }

    /**
     * Configured password hashes are updated through the deployment environment.
     *
     * @param  array<string, mixed>  $credentials
     */
    public function rehashPasswordIfRequired(Authenticatable $user, #[SensitiveParameter] array $credentials, bool $force = false): void {}

    private function user(): ?GenericUser
    {
        if ($this->email === '' || password_get_info($this->passwordHash)['algo'] === null) {
            return null;
        }

        return new GenericUser([
            'id' => hash('sha256', $this->email.'|'.$this->passwordHash),
            'name' => 'Administrator',
            'email' => $this->email,
            'password' => $this->passwordHash,
            'remember_token' => null,
        ]);
    }
}
