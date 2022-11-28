<?php

namespace mindtwo\PxUserLaravel\Listeners;

use mindtwo\PxUserLaravel\Events\PxUserLoginEvent;
use App\Enums\RoleEnum;

class UserLoginListener
{
    /**
     * Handle the event.
     *
     * @param  \App\Events\OrderShipped  $event
     * @return void
     */
    public function handle(PxUserLoginEvent $event)
    {
        $user = $event->user;
        $adminEmails = config('px-user.admin_emails');

        if (!empty($adminEmails) && in_array($event->userData['email'], $adminEmails)) {
            $user->role = RoleEnum::Admin;
            $user->save();
        }
    }
}
