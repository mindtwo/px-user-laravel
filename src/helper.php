<?php

use mindtwo\PxUserLaravel\Services\PxUserService;

if (! function_exists('px_user')) {

    /**
     * Get the PxUserService
     *
     * @deprecated Use the PxUserService facade instead.
     */
    function px_user(): PxUserService
    {
        return resolve(PxUserService::class);
    }
}
