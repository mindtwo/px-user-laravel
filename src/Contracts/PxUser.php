<?php

namespace mindtwo\PxUserLaravel\Contracts;

interface PxUser
{
    /**
     * Get the user's PX User ID.
     */
    public function getPxUserId(): string;

    /**
     * Get the user's PX User domain code.
     */
    public function getPxUserDomainCode(): string;

    /**
     * Get the user's PX User tenant code.
     */
    public function getPxUserTenantCode(): string;

    /**
     * Get the user's PX User access token.
     */
    public function getPxUserAccessToken(): string;

    /**
     * Check if user has valid px user token.
     */
    public function hasValidPxUserToken(): bool;
}
