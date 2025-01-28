<?php

namespace mindtwo\PxUserLaravel\Events;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use mindtwo\PxUserLaravel\Data\PxUserPermissionData;

class PxUserLoginEvent
{
    use Dispatchable;
    use SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public Authenticatable $user,
        public PxUserPermissionData $userData,
        public string $accessToken,
    ) {}
}
