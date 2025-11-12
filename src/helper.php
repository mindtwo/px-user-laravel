<?php

if (! function_exists('px_user')) {
    function px_user(): \mindtwo\PxUserLaravel\Services\PxUserService
    {
        return app(\mindtwo\PxUserLaravel\Services\PxUserService::class);
    }
}
