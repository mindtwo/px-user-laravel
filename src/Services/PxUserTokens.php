<?php

namespace mindtwo\PxUserLaravel\Services;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Carbon;
use mindtwo\PxUserLaravel\Models\PxUserToken;
use RuntimeException;

class PxUserTokens
{
    /**
     * Loaded tokens, keyed per authenticatable and validity scope.
     *
     * @var array<string, PxUserToken>
     */
    private array $tokens = [];

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

        $saved = PxUserToken::query()
            ->create([
                'authenticatable_type' => get_class($authenticatable),
                'authenticatable_id' => $authenticatable->getAuthIdentifier(),
                'token_data' => $token,
                'valid_until' => $validUntil,
            ]);

        $this->tokens[$this->tokenKey($authenticatable, true)] = $saved;

        if ($saved->isValid()) {
            $this->tokens[$this->tokenKey($authenticatable, false)] = $saved;
        }

        return $saved;
    }

    public function invalidate(Authenticatable $authenticatable): bool
    {
        unset(
            $this->tokens[$this->tokenKey($authenticatable, true)],
            $this->tokens[$this->tokenKey($authenticatable, false)],
        );

        return PxUserToken::query()
            ->forAuthenticatable($authenticatable)
            ->update([
                'valid_until' => now(),
            ]) > 0;
    }

    public function isCurrentTokenValid(Authenticatable $authenticatable): bool
    {
        try {
            $expiresAt = $this->expiresAt($authenticatable);

            return $expiresAt->isFuture();
        } catch (RuntimeException) {
            return false;
        }
    }

    public function canRefreshCurrentToken(Authenticatable $authenticatable): bool
    {
        return ! empty($this->refreshToken($authenticatable));
    }

    public function accessToken(Authenticatable $authenticatable): string
    {
        $token = $this->getToken($authenticatable);

        throw_if(! $token, new RuntimeException('No token found for authenticatable'));

        return $token->token('access_token');
    }

    public function expiresAt(Authenticatable $authenticatable): ?Carbon
    {
        $token = $this->getToken($authenticatable);

        throw_if(! $token, new RuntimeException('No token found for authenticatable'));

        return $token->valid_until;
    }

    public function refreshToken(Authenticatable $authenticatable): ?string
    {
        $token = $this->getToken($authenticatable, true);

        return $token?->token('refresh_token');
    }

    public function refreshTokenValidUntil(Authenticatable $authenticatable): ?Carbon
    {
        $token = $this->getToken($authenticatable, true);
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
    protected function getToken(Authenticatable $authenticatable, bool $includeInvalid = false): ?PxUserToken
    {
        $key = $this->tokenKey($authenticatable, $includeInvalid);

        if (isset($this->tokens[$key])) {
            return $this->tokens[$key];
        }

        $token = PxUserToken::query()
            ->forAuthenticatable($authenticatable)
            ->when(! $includeInvalid, fn ($q) => $q->valid())
            ->latest()
            ->first();

        throw_if(! $token, new RuntimeException('No token found for authenticatable'));

        return $this->tokens[$key] = $token;
    }

    /**
     * Get the key a loaded token is kept under.
     */
    protected function tokenKey(Authenticatable $authenticatable, bool $includeInvalid): string
    {
        return get_class($authenticatable).':'.$authenticatable->getAuthIdentifier().':'.($includeInvalid ? 'any' : 'valid');
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
