<?php

namespace mindtwo\PxUserLaravel\Helper;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Session;

class SessionHelper
{
    /**
     * Save token data either to cache or session
     *
     * @param Request $request
     * @param array $tokenData
     * @return void
     */
    public static function saveTokenData(array $tokenData, ?Request $request = null): void
    {
        // TODO remove?
        // if ($request->is('api/*')) {
        //     $accessTokenPrefix = (self::prefix() . ':cached_access_token_' . $request->user()->px_user_id);
        //     $refreshTokenPrefix = (self::prefix() . ':cached_refresh_token_' . $request->user()->px_user_id);

        //     Cache::put($accessTokenPrefix, $tokenData['access_token'], Carbon::parse($tokenData['access_token_expiration_utc']));

        //     Cache::put($refreshTokenPrefix, $tokenData['refresh_token'], Carbon::parse($tokenData['refresh_token_expiration_utc']));

        //     return;
        // }

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
     * @param Request $request
     * @return void
     */
    public static function flush(Request $request)
    {
        // on request from api remove from cache
        // TODO remove?
        // if ($request->is('api/*')) {
        //     $accessTokenPrefix = (self::prefix() . ':cached_access_token_' . $request->user()->px_user_id);
        //     $refreshTokenPrefix = (self::prefix() . ':cached_refresh_token_' . $request->user()->px_user_id);

        //     Cache::forget($accessTokenPrefix);
        //     Cache::forget($refreshTokenPrefix);
        // }

        // $request->session()->invalidate();
        // $request->session()->regenerateToken();

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
        // on request from api get from cache
        // TODO remove?
        // if (request()->is('api/*')) {
        //     $cacheKey = self::prefix() . ":cached_{$key}_" . request()->user()->px_user_id;

        //     return Cache::get($cacheKey);
        // }

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
