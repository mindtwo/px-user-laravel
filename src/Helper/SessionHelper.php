<?php

namespace mindtwo\PxUserLaravel\Helper;

use Illuminate\Support\Facades\Session;

class SessionHelper
{
    /**
     * Save token data either to cache or session
     *
     * @param array $tokenData
     * @return void
     */
    public static function saveTokenData(array $tokenData): void
    {
        $accessTokenKeys = ['access_token', 'access_token_expiration_utc', 'refresh_token', 'refresh_token_expiration_utc'];
        foreach ($accessTokenKeys as $key) {
            $sessionKey = self::prefix() . "_$key";
            // Todo: set last user token

            Session::put($sessionKey, $tokenData[$key]);
        }
    }

    /**
     * Remove session data for request
     *
     * @return void
     */
    public static function flush()
    {
        Session::invalidate();
        Session::regenerateToken();
    }

    /**
     * Get value for passed key
     *
     * @param string $key
     * @return mixed
     */
    public static function get(string $key): mixed
    {
        $sessionKey = $key;
        if (!str_starts_with($sessionKey, self::prefix())) {
            $sessionKey = self::prefix() . "_$sessionKey";
        }

        return Session::get($sessionKey);
    }

    private static function prefix(): string
    {
        return config('px-user.session_prefix') ?? 'px_user';
    }
}
