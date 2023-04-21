<?php

namespace mindtwo\PxUserLaravel\Services;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use mindtwo\PxUserLaravel\Contracts\AccessTokenHelper as ContractsAccessTokenHelper;

class WebAccessTokenHelper extends ContractsAccessTokenHelper
{
    public function __construct(
        // protected Guard $guard,
        // protected ?Authenticatable $user = null,
    ) {
    }

    /**
     * Remove session data for request
     *
     * @return void
     */
    public function flush()
    {
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
    public function put(string $key, string $value)
    {
        if (!$this->allowed($key)) {
            throw new \Exception("Error Processing Request", 1);
        }

        $sessionKey = $this->prefix() . "_$key";
        Session::put($sessionKey, $value);
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
        return Session::get($sessionKey);
    }
}
