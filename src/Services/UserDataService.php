<?php

namespace mindtwo\PxUserLaravel\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use mindtwo\PxUserLaravel\Actions\PxUserGetDetailsAction;
use mindtwo\PxUserLaravel\Actions\PxUserDataRefreshAction;

class UserDataService
{
    public function __construct(
        protected PxUserDataRefreshAction $pxUserDataRefreshAction,
        protected PxUserGetDetailsAction $pxUserDataGetDetailsAction,
    ) {
    }

        /*
    public function getPxUserDataAttribute(): null|Collection
    {
        // Todo: moves this to UseUserDataCache
        return Cache::remember('user: '.$this->uuid, 2628288, function () {
            try {
                if (empty($this->tenant_code) || empty($this->domain_code)) {
                    throw new \Exception('Tenant code or domain code is not set.');
                }

                $platform = Platform::where([
                    'px_user_tenant' => $this->tenant_code,
                    'px_user_domain' => $this->domain_code,
                ])->first();

                // @phpstan-ignore-next-line
                return collect(app(PxUserClient::class)->setCredentials(
                    $platform?->px_user_tenant ?? config('px-user.tenant'),
                    $platform?->px_user_domain ?? config('px-user.domain'),
                    $platform?->px_user_secret ?? config('px-user.m2m_credentials')
                )->getUserData(auth()->user()?->last_px_user_access_token));
            } catch (\Throwable $e) {
                // Todo: remove try and placeholder dummy data
                return collect([
                    'firstname' => fake()->firstName,
                    'lastname' => fake()->lastName,
                    'preferred_username' => null,
                    'email' => fake()->email,
                    'staff_id' => fake()->numberBetween(100000, 999999),
                    'supervisor' => fake()->firstName.' '.fake()->lastName,
                ]);
            }
        });
    }
    */

    // Todo tenant/domain code
    /**
     * Try to get cached UserData.
     * If no data are in Cache, try to
     * refresh them.
     *
     * @param $px_user_id
     * @return mixed|void
     */
    public function getUserData(string $px_user_id)
    {
        if (App::environment(['testing'])) {
            return [];
        }

        // get data for other user
        if ($px_user_id !== Auth::user()->{config('px-user.px_user_id')}) {
            $auth_user_id = Auth::user()->{config('px-user.px_user_id')};

            $cachePrefix = "user:cached_{$auth_user_id}:user_{$px_user_id}";

            $data = $this->pxUserDataGetDetailsAction->execute($px_user_id);

            return $data;
        }

        // Data for current user are cached via request middleware
        // Todo changeable domains/tenant in product
        // cache prefix
        $cachePrefix = ('user:cached_'.$px_user_id);

        // get user data from cache
        $userData = Cache::get($cachePrefix);

        // if we have no cached data forget delete old ones from cache
        if (empty($userData)) {
            Cache::forget($cachePrefix);
        }

        return $userData;
    }

    /**
     * Refresh data for current request
     *
     * @param Request $request
     * @return void
     */
    public function refreshUserData(Request $request)
    {
        $px_user_id = $request->user()->{config('px-user.px_user_id')};

        $cachePrefix = ('user:cached_'.$px_user_id);
        return Cache::remember(
            $cachePrefix,
            now()->addMinutes(config('px-user.px_user_cache_time')),
            fn () => $this->pxUserDataRefreshAction->execute($request)
        );
    }
}
