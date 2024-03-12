<?php

namespace mindtwo\PxUserLaravel\Driver\Session;

use Illuminate\Support\Carbon;
use mindtwo\PxUserLaravel\Driver\Contracts\ExpirationHelper as ContractsExpirationHelper;
use mindtwo\PxUserLaravel\Facades\PxUser;

class ExpirationHelper implements ContractsExpirationHelper
{
    /**
     * Check if access token is expired.
     */
    public function accessTokenExpired(): bool
    {
        if (PxUser::isFaking()) {
            return false;
        }

        if (null == ($time = $this->get('access_token_expiration_utc'))) {
            return true;
        }

        return Carbon::now()->gt($time);
    }

    /**
     * Check if access token is expiring soon.
     */
    public function accessTokenExpiringSoon(): bool
    {
        if (PxUser::isFaking()) {
            return false;
        }

        if (null == ($time = $this->get('access_token_expiration_utc'))) {
            return false;
        }

        return Carbon::now()->addMinutes(2)->gte($time);
    }

    /**
     * Check if tokens can be refreshed.
     */
    public function canRefresh(): bool
    {
        if ($this->get('refresh_token') === null || ($time = $this->get('refresh_token_expiration_utc')) === null) {
            return false;
        }

        return Carbon::now()->lt($time);
    }

    private function get(string $key): mixed
    {
        return (new AccessTokenHelper())->get($key);
    }
}
