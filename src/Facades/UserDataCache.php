<?php

namespace mindtwo\PxUserLaravel\Facades;

use Illuminate\Support\Facades\Facade;
use mindtwo\PxUserLaravel\Services\UserDataService;

/**
 * @method static mixed|void getUserData(string $px_user_id)
 * @method static mixed refreshUserData(\Illuminate\Http\Request $request)
 */
class UserDataCache extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     *
     * @throws \RuntimeException
     */
    protected static function getFacadeAccessor()
    {
        return UserDataService::class;
    }
}
