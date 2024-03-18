<?php

namespace mindtwo\PxUserLaravel\Driver\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;

interface SessionDriver
{
    public function newAccessTokenHelper(Authenticatable $user): AccessTokenHelper;

    public function getAccessTokenHelper(): ?AccessTokenHelper;

    public function getExpirationHelper(): ?ExpirationHelper;

    public function driver(): self;

    /**
     * Get the tenant of the current session.
     */
    public function getTenant(): string;

    /**
     * Return the domain of the current session.
     */
    public function getDomain(): string;

    /**
     * Return if the current session is valid.
     */
    public function validate(): bool;

    /**
     * Login a user.
     *
     * @param  array  $tokenData  The token from px-user to login with.
     */
    public function login(array $tokenData): ?self;

    /**
     * Refresh the current session.
     */
    public function refresh(Authenticatable $authenticatable, ?string $refreshToken = null): null|bool|array;

    /**
     * Logout the current session.
     */
    public function logout(): bool;

    public function userId(): null|int|string;

    public function user(): ?Authenticatable;

    public function setUser(Authenticatable $user): void;
}
