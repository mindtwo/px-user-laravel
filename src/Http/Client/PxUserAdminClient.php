<?php

namespace mindtwo\PxUserLaravel\Http\Client;

use Illuminate\Http\Client\PendingRequest;
use mindtwo\PxUserLaravel\DataTransfer\PxUserData;
use mindtwo\TwoTility\Http\BaseApiClient;
use RuntimeException;

class PxUserAdminClient extends BaseApiClient
{
    /**
     * Custom M2M credentials to override config value.
     */
    protected ?string $m2mCredentials = null;

    public function __construct()
    {
        throw_if(! app()->runningInConsole(), new RuntimeException('PxUserAdminClient can only be used in console context.'));
    }

    /**
     * Set custom M2M credentials.
     * Allows consuming applications to override the default config credentials.
     */
    public function withM2mCredentials(string $credentials): static
    {
        $this->m2mCredentials = $credentials;

        return $this;
    }

    /**
     * Get data for px-user with m2m
     */
    public function getUser(string $userId): ?PxUserData
    {
        $userData = $this->client()->get("user/$userId")
            ->json('response');

        if (! isset($userData['user'])) {
            return null;
        }

        return PxUserData::from($userData['user']);
    }

    public function apiName(): string
    {
        return 'px-user';
    }

    /**
     * Get the config key for client configuration.
     */
    protected function configBaseKey(): string
    {
        return 'px-user.apiClient';
    }

    /**
     * Hook before client configuration.
     * Ensures the admin client is only used in console context.
     */
    protected function beforeConfigure(PendingRequest $client): void
    {
        if (! app()->runningInConsole()) {
            throw new \RuntimeException('PxUserAdminClient can only be used in console context.');
        }
    }

    /**
     * Hook called after configuration.
     * Adds M2M authorization header.
     */
    protected function afterConfigure(PendingRequest $client): void
    {
        $credentials = $this->m2mCredentials ?? config('px-user.m2m_credentials');

        $client->withHeader('x-m2m-authorization', $credentials);
    }
}
