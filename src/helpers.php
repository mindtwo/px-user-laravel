<?php

if (! function_exists('pxSession')) {
    function pxSession(): \mindtwo\PxUserLaravel\Driver\Contracts\SessionDriver
    {
        return pxUser()->session();
    }
}

if (! function_exists('pxUser')) {
    function pxUser(): \mindtwo\PxUserLaravel\Services\PxUserService
    {
        return app('px-user');
    }
}
