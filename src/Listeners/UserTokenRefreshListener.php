<?php

namespace mindtwo\PxUserLaravel\Listeners;

use mindtwo\PxUserLaravel\Events\PxUserTokenRefreshEvent;

class UserTokenRefreshListener
{
    /**
     * Handle the event.
     *
     * @param  PxUserTokenRefreshEvent  $event
     * @return void
     */
    public function handle(PxUserTokenRefreshEvent $event): void
    {
        if (config('px-user.sanctum.enabled') === true && class_exists(\Laravel\Sanctum\Sanctum::class)) {
            $currentToken = $event->user->currentAccessToken();

            $currentToken->update([
                'linked_px_user_token' => $event->newAccessToken,
            ]);
        }
    }
}
