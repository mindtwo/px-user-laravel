<?php

namespace mindtwo\PxUserLaravel\Services;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Carbon;
use mindtwo\PxUserLaravel\Models\PxUserToken;
use RuntimeException;

class PxUserTokens
{
    private PxUserToken $currentToken;

    /**
     * {@inheritDoc}
     */
    public function current(Authenticatable $authenticatable): array
    {
        $token = $this->getToken($authenticatable);

        throw_if(! $token?->token_data, new RuntimeException('No token found for authenticatable'));

        return $token->token_data;
    }

    public function save(Authenticatable $authenticatable, array $token): PxUserToken
    {
        // Invalidate existing token if it exists
        $this->invalidate($authenticatable);

        $validUntil = $this->parseValidUntil($token);

        return PxUserToken::query()
            ->create([
                'authenticatable_type' => get_class($authenticatable),
                'authenticatable_id' => $authenticatable->getAuthIdentifier(),
                'token_data' => $token,
                'valid_until' => $validUntil,
            ]);
    }

    /**
     * {@inheritDoc}
     */
    public function invalidate(Authenticatable $authenticatable): bool
    {
        return PxUserToken::query()
            ->forAuthenticatable($authenticatable)
            ->update([
                'valid_until' => now(),
            ]) > 0;
    }

    /**
     * {@inheritDoc}
     */
    public function isCurrentTokenValid(Authenticatable $authenticatable): bool
    {
        try {
            $expiresAt = $this->expiresAt($authenticatable);

            return $expiresAt->isFuture();
        } catch (RuntimeException) {
            return false;
        }
    }

    /**
     * {@inheritDoc}
     */
    public function canRefreshCurrentToken(Authenticatable $authenticatable): bool
    {
        return !empty($this->refreshToken($authenticatable));
    }

    /**
     * {@inheritDoc}
     */
    public function accessToken(Authenticatable $authenticatable): string
    {
        $token = $this->getToken($authenticatable);

        throw_if(! $token, new RuntimeException('No token found for authenticatable'));

        return $token->token('access_token');
    }

    /**
     * {@inheritDoc}
     */
    public function expiresAt(Authenticatable $authenticatable): ?Carbon
    {
        $token = $this->getToken($authenticatable);

        throw_if(! $token, new RuntimeException('No token found for authenticatable'));

        return $token->valid_until;
    }

    /**
     * {@inheritDoc}
     */
    public function refreshToken(Authenticatable $authenticatable): ?string
    {
        $token = $this->getToken($authenticatable);

        return $token?->token('refresh_token');
    }

    /**
     * {@inheritDoc}
     */
    public function refreshTokenValidUntil(Authenticatable $authenticatable): ?Carbon
    {
        $token = $this->getToken($authenticatable);
        $validUntil = $token?->token('refresh_token_expiration_utc');

        if ($validUntil instanceof Carbon) {
            return $validUntil;
        }

        if (is_string($validUntil)) {
            return Carbon::parse($validUntil);
        }

        return null;
    }

    /**
     * Get the token model for the authenticatable.
     */
    protected function getToken(Authenticatable $authenticatable): ?PxUserToken
    {
        if (isset($this->currentToken)) {
            return $this->currentToken;
        }

        $token = PxUserToken::query()
            ->forAuthenticatable($authenticatable)
            ->latest()
            ->first();

        throw_if(! $token, new RuntimeException('No token found for authenticatable'));

        $this->currentToken = $token;

        return $this->currentToken;
    }

    /**
     * Parse the valid_until timestamp from token data.
     */
    protected function parseValidUntil(array $token): ?Carbon
    {
        $expiresAt = $token['access_token_expiration_utc'];

        return $expiresAt instanceof Carbon ? $expiresAt : Carbon::parse($expiresAt);
    }
}
