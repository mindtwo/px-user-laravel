<?php

namespace mindtwo\PxUserLaravel\Services;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Cache;
use mindtwo\PxUserLaravel\Contracts\AccessTokenHelper as ContractsAccessTokenHelper;

class SanctumAccessTokenHelper extends ContractsAccessTokenHelper
{
    public function __construct(
        protected Authenticatable $user,
    ) {
    }

    /**
     * Remove session data for request
     *
     * @return void
     */
    public function flush()
    {
        foreach ($this->allowedKeys() as $key) {
            $sessionKey = $this->prefix() . "_$key";

            Cache::forget($sessionKey);
        }
    }

    /**
     * Put value for passed key
     *
     * @param string $key
     * @param string $value
     * @return void
     */
    public function put(string $key, string $value)
    {
        if (!$this->allowed($key)) {
            throw new \Exception("Error Processing Request", 1);
        }

        $sessionKey = $this->prefix() . "_$key";

        Cache::put($sessionKey, $value);
    }

    /**
     * Get value for passed key
     *
     * @param string $key
     * @return mixed
     */
    public function get(string $key): mixed
    {
        if (!$this->allowed($key)) {
            throw new \Exception("Error Processing Request", 1);
        }

        $sessionKey = $this->prefix() . "_$key";

        return Cache::get($sessionKey);
    }

    public function prefix(): string
    {
        $prefix = config('px-user.session_prefix') ?? 'px_user';
        $px_user_id = $this->user->{config('px-user.px_user_id')};

        return "$px_user_id:$prefix";
    }
}
