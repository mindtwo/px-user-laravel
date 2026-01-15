<?php

namespace mindtwo\PxUserLaravel\ExternalApiTokens;

use Illuminate\Contracts\Auth\Authenticatable;
use mindtwo\PxUserLaravel\Http\Client\PxUserClient;
use mindtwo\PxUserLaravel\PxUser;

trait RefreshesTokens
{
    /**
     * {@inheritDoc}
     */
    public function refresh(string $refreshToken): bool
    {
        // Refresh tokens
        $newTokens = resolve(PxUserClient::class)->refreshTokens($refreshToken);

        // Get user by token
        /** @var Authenticatable $user */
        [$userData, $user] = resolve(PxUser::class)->resolveByToken($newTokens);

        // Save the tokens
        $this->save($user, $newTokens);

        return true;
    }
}
