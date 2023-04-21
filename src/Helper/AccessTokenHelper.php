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

            if (isset($tokenData[$key])) {
                self::put($sessionKey, $tokenData[$key]);
            }
        }
    }

    /**
     * Get px user token data for current user
     *
     * @return array
     */
    public static function values()
    {
        return collect(self::$accessTokenKeys)
            ->mapWithKeys(fn ($key) => [$key => self::get($key)])
            ->toArray();
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
        if (!self::allowed($key)) {
            throw new \Exception("Error Processing Request", 1);
        }

        $sessionKey = self::prefix() . "_$key";

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
        if (!self::allowed($key)) {
            throw new \Exception("Error Processing Request", 1);
        }

        $sessionKey = self::prefix() . "_$key";

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

    /**
     * Check if key is in array of
     * keys which are allowed to be handled
     * by this helper.
     *
     * @param string $key
     * @return boolean
     */
    private static function allowed(string $key): bool
    {
        return in_array($key, self::$accessTokenKeys);
    }
}
