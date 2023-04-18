<?php

namespace mindtwo\PxUserLaravel\Actions;

use Illuminate\Contracts\Auth\Authenticatable;

class RetrieveUserOnLoginAction
{

    /**
     * Ivokeable which returns a model implementing Authenticatable Contract.
     *
     * @return bool|Authorizable
     */
    public function __invoke(array $requestData): bool|Authenticatable
    {
        if (null === config('px-user.user_model')) {
            return false;
        }

        return config('px-user.user_model')::firstOrCreate([
            config('px-user.px_user_id') => $requestData['id'],
        ]);
    }

}
