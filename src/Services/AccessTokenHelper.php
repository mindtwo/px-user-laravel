<?php

namespace mindtwo\PxUserLaravel\Services;

use mindtwo\PxUserLaravel\Contracts\AccessTokenHelper as ContractsAccessTokenHelper;

class AccessTokenHelper extends ContractsAccessTokenHelper
{
    /**
     * Remove session data for request
     *
     * @return void
     */
    public function flush()
    {
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
    }

    /**
     * Get value for passed key
     *
     * @param string $key
     * @return mixed
     */
    public function get(string $key): mixed
    {
        return null;
    }
}
