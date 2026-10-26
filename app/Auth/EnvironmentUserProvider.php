<?php

namespace App\Auth;

use Illuminate\Auth\GenericUser;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Support\Facades\Hash;
use SensitiveParameter;

class EnvironmentUserProvider implements UserProvider
{
    public const DEMO_PASSWORD_HASH = '$2y$12$g4.HyJWKUly61h4GrfsNKeKFEYmQgUvRWKp4cskUjTJ2ge17BjHWK';

    public function __construct(private string $username, #[SensitiveParameter] private string $passwordHash) {}

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
        $username = $credentials['username'] ?? null;

        return is_string($username) && hash_equals($this->username, mb_strtolower($username)) ? $this->user() : null;
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
        if ($this->username === '' || password_get_info($this->passwordHash)['algo'] === null) {
            return null;
        }

        return new GenericUser([
            'id' => hash('sha256', $this->username.'|'.$this->passwordHash),
            'name' => 'Administrator',
            'username' => $this->username,
            'password' => $this->passwordHash,
        ]);
    }
}
