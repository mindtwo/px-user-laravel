<?php

namespace mindtwo\PxUserLaravel\Listeners;

use mindtwo\PxUserLaravel\Events\PxUserLoginEvent;

class UserLoginListener
{
    /**
     * Handle the event.
     *
     * @param  PxUserLoginEvent  $event
     * @return void
     */
    public function handle(PxUserLoginEvent $event): void
    {
        $user = $event->user;
        $adminEmails = config('px-user.admin_emails');
        $adminRoleValue = config('px-user.admin_role_value');

        if (! empty($adminEmails) && isset($adminRoleValue) && in_array($event->userData['email'], $adminEmails)) {
            $user->role = $adminRoleValue;
            $user->save();
        }
    }
}
