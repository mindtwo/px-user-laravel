<?php

namespace mindtwo\PxUserLaravel\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * TODO merge this and PxUserSession Facade
 * @method static self fake()
 * @method static bool isFaking()
 */
class PxUser extends Facade
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
        return 'px-user';
    }
}
