<?php

namespace mindtwo\PxUserLaravel\Actions;

use Illuminate\Contracts\Auth\Access\Authorizable;

class RetrieveUserOnLoginAction
{

    /**
     * Ivokeable which returns a model implementing Authorizeable Contract.
     *
     * @return bool|Authorizable
     */
    public function __invoke(array $requestData): bool|Authorizable
    {
        if (null === config('px-user.user_model')) {
            return false;
        }

        return config('px-user.user_model')::firstOrCreate([
            config('px-user.px_user_id') => $requestData['id'],
        ]);
    }

}
