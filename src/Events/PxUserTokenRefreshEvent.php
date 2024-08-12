<?php

namespace mindtwo\PxUserLaravel\Events;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PxUserTokenRefreshEvent
{
    use Dispatchable;
    use SerializesModels;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct(
        public Authenticatable $user,
        public string $newAccessToken,
    ) {}
}
