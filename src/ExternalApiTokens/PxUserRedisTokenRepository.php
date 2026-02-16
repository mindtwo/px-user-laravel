<?php

namespace mindtwo\PxUserLaravel\ExternalApiTokens;

use mindtwo\TwoTility\ExternalApiTokens\Redis\RedisExternalApiTokenRepository;

class PxUserRedisTokenRepository extends RedisExternalApiTokenRepository
{
    use RefreshesTokens;

    /**
     * Create a new PxUser Redis token repository instance.
     */
    public function __construct()
    {
        parent::__construct(
            apiName: 'px-user',
            keyMapping: [
                'access_token' => 'access_token',
                'refresh_token' => 'refresh_token',
                'expires_at' => 'access_token_expiration_utc',
                'refresh_token_valid_until' => 'refresh_token_expiration_utc',
            ],
        );
    }
}
