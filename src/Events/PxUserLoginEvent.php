<?php

namespace mindtwo\PxUserLaravel\Events;

use Illuminate\Queue\SerializesModels;
use mindtwo\PxUserLaravel\Contracts\PxUser;
use mindtwo\PxUserLaravel\DataTransfer\PxUserData;
use mindtwo\PxUserLaravel\DataTransfer\PxUserDataWithPermissions;

class PxUserLoginEvent
{
    use SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public PxUser $user,
        public PxUserData|PxUserDataWithPermissions $userData,
        public bool $isFirstLogin = false,
    ) {}
}
