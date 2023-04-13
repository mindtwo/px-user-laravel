<?php

namespace mindtwo\PxUserLaravel\Helper;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Session;

class AccessTokenHelper
{

    private static $accessTokenKeys = ['access_token', 'access_token_expiration_utc', 'refresh_token', 'refresh_token_expiration_utc'];

    /**
     * Save token data either to cache or session
     *
     * @param array $tokenData
     * @return void
     */
    public static function saveTokenData(array $tokenData): void
    {
        foreach (self::$accessTokenKeys as $key) {
            $sessionKey = self::prefix() . "_$key";

            self::put($sessionKey, $tokenData[$key]);
        }
    }

    /**
     * Remove session data for request
     *
     * @return void
     */
    public static function flush()
    {
        if (auth('sanctum')->check()) {
            foreach (self::$accessTokenKeys as $key) {
                $sessionKey = self::prefix() . "_$key";

                Cache::forget($sessionKey);
            }

            return;
        }

        Session::invalidate();
        Session::regenerateToken();
    }

    /**
     * Put value for passed key
     *
     * @param string $key
     * @param string $value
     * @return void
     */
    public static function put(string $key, string $value)
    {
        $sessionKey = $key;
        if (!str_starts_with($sessionKey, self::prefix())) {
            $sessionKey = self::prefix() . "_$sessionKey";
        }

        if (auth('sanctum')->check()) {
            Cache::put($sessionKey, $value);
            return;
        }

        Session::put($sessionKey, $value);
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

        if (auth('sanctum')->check()) {
            return Cache::get($sessionKey);
        }

        return Session::get($sessionKey);
    }

    private static function prefix(): string
    {
        $prefix = config('px-user.session_prefix') ?? 'px_user';

        if (auth('sanctum')->check()) {
            $px_user_id = auth('sanctum')->user()->{config('px-user.px_user_id')};

            return "$px_user_id:$prefix";
        }

        return $prefix;
    }
}
