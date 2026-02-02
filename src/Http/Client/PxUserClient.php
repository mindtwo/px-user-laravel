<?php

namespace mindtwo\PxUserLaravel\Http\Client;

use Illuminate\Http\Client\PendingRequest;
use mindtwo\PxUserLaravel\Contracts\PxUser;
use mindtwo\PxUserLaravel\DataTransfer\PxUserData;
use mindtwo\PxUserLaravel\DataTransfer\PxUserDataWithPermissions;
use mindtwo\TwoTility\Http\BaseApiClient;
use RuntimeException;

class PxUserClient extends BaseApiClient
{
    /**
     * Optional override for domain code.
     */
    protected ?string $domainCodeOverride = null;

    /**
     * Optional override for tenant code.
     */
    protected ?string $tenantCodeOverride = null;

    /**
     * Optional override for access token.
     */
    protected ?string $accessTokenOverride = null;

    /**
     * Get currently authed users data.
     */
    public function getUser(): PxUserData
    {
        $response = $this->client()->get('v1/user')
            ->json('response');

        if (! isset($response['user'])) {
            throw new RuntimeException('Empty user returned');
        }

        return PxUserData::from($response['user']);
    }

    /**
     * Get the details for a user or list of users.
     *
     * @param  string|array<int, string>  $userIds  Single user ID or array of user IDs
     * @return ($userIds is string ? PxUserData : array<int, PxUserData>)
     */
    public function getUsersDetails(string|array $userIds): PxUserData|array
    {
        $single = is_string($userIds);

        if ($single) {
            $userIds = [$userIds];
        }

        $response = $this->client()->post('v1/users/details', [
            'user_ids' => $userIds,
        ])->json('response');

        if ($single) {
            return PxUserData::from($response[0]);
        }

        return PxUserData::collect($response, 'array');
    }

    /**
     * Get currently authed users data with permissions.
     */
    public function getUserWithPermissions(bool $withExtendedProducts = false): PxUserDataWithPermissions
    {
        $response = $this->client()->get('v1/user-with-permissions', [
            'withExtendedProducts' => $withExtendedProducts,
        ])->json('response');

        if (! isset($response['user'])) {
            throw new RuntimeException('Empty user returned');
        }

        return PxUserDataWithPermissions::from($response['user']);
    }

    /**
     * Get users list with search.
     */
    public function getUsers(string $names, string $productContext): array
    {
        $response = $this->client()
            ->withHeader('X-Context-Product-Code', $productContext)
            ->get('v1/users', [
                'names' => $names,
            ]);

        $data = $response->json('response', [])['data'] ?? [];

        return $data;
    }

    public function refreshTokens(string $refreshToken): array
    {
        // Overrides are required since we maybe dont have a user here.
        $this->setAccessToken($refreshToken);

        $response = $this->client()->get('v1/refresh-tokens');

        return $response->json('response');
    }

    /**
     * Check eip connection for currently authed user.
     */
    public function checkEipConnection(): array
    {
        $response = $this->client()->get('/v2/eip/check');

        return $response->json();
    }

    /**
     * Set domain code override.
     *
     * @return $this
     */
    public function setDomainCode(?string $domainCode): static
    {
        $this->domainCodeOverride = $domainCode;

        return $this;
    }

    /**
     * Set tenant code override.
     *
     * @return $this
     */
    public function setTenantCode(?string $tenantCode): static
    {
        $this->tenantCodeOverride = $tenantCode;

        return $this;
    }

    /**
     * Set access token override.
     *
     * @return $this
     */
    public function setAccessToken(?string $accessToken): static
    {
        $this->accessTokenOverride = $accessToken;

        return $this;
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
     * Hook called after configuration.
     * Allows custom client configuration via config.
     */
    protected function afterConfigure(PendingRequest $client): void
    {
        // Determine if we need to get the authenticated user
        $needsUser = $this->accessTokenOverride === null;
        // || $this->tenantCodeOverride === null
        // || $this->domainCodeOverride === null;

        $user = null;
        if ($needsUser) {
            $user = auth()->user();
            abort_if(! $user instanceof PxUser, 401, 'Unauthenticated.');
        }

        // Add bearer token
        $accessToken = $this->accessTokenOverride ?? $user?->getPxUserAccessToken();
        if ($accessToken) {
            $client->withToken($accessToken);
        }

        $context = array_filter([
            'x-context-tenant-code' => $this->tenantCodeOverride ?? $user?->getPxUserTenantCode(),
            'x-context-domain-code' => $this->domainCodeOverride ?? $user?->getPxUserDomainCode(),
        ]);

        if (! empty($context)) {
            $client->withHeaders($context);
        }

    }
}
