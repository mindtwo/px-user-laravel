<?php

namespace mindtwo\PxUserLaravel\Events;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Queue\SerializesModels;
use mindtwo\PxUserLaravel\DataTransfer\PxUserDataWithPermissions;

class PxUserTokenRefreshEvent
{
    use SerializesModels;

    /**
     * Create a new event instance.
     *
     * `$userData` is the user's px-user data fetched live with the new access token.
     */
    public function __construct(
        public Authenticatable $user,
        public string $newAccessToken,
        public PxUserDataWithPermissions $userData,
    ) {}
}
