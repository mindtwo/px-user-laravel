<?php

namespace mindtwo\PxUserLaravel\Traits;

use Illuminate\Support\Carbon;

trait HasRefreshableApiTokens {

    use \Laravel\Sanctum\HasApiTokens;

    public function createAccessToken(string $name, ?Carbon $expiresAt = null, string $refresh_token, array $abilities = ['*']): \Laravel\Sanctum\NewAccessToken
    {
        $plainTextToken = $this->generateTokenString();

        $token = $this->tokens()->create([
            'name' => $name,
            'token' => hash('sha256', $plainTextToken),
            'abilities' => $abilities,
            'expires_at' => $expiresAt,
            'refresh_token' => $refresh_token,
        ]);

        return new \Laravel\Sanctum\NewAccessToken($token, $token->getKey().'|'.$plainTextToken);
    }

    public function refreshAccessToken(string $refreshToken, ?Carbon $newExpiresAt = null): \Laravel\Sanctum\NewAccessToken
    {
        $plainTextToken = $this->generateTokenString();

        $token = $this->tokens()->where('refresh_token', $refreshToken)->first();

        if (! $token) {
            throw new \RuntimeException('Token not found');
        }

        $token->token = hash('sha256', $plainTextToken);

        if (!is_null($newExpiresAt)) {
            $token->expires_at = $newExpiresAt;
        }

        $token->refresh_token = $refreshToken;
        $token->save();

        return new \Laravel\Sanctum\NewAccessToken($token, $token->getKey().'|'.$plainTextToken);
    }

}
