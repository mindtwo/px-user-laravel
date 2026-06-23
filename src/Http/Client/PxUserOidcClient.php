<?php

namespace mindtwo\PxUserLaravel\Http\Client;

use mindtwo\TwoTility\Http\BaseApiClient;

class PxUserOidcClient extends BaseApiClient
{
    private string $redirectUri;

    private string $clientId;

    private ?string $pxUserTenant = null;

    private ?string $pxUserDomain = null;

    /**
     * Exchange an authorization code (with PKCE verifier) for IdP tokens.
     *
     * @return array{access_token: string, refresh_token?: string, expires_in: int, id_token?: string, token_type: string, scope?: string}
     */
    public function exchangeToken(string $code, string $codeVerifier): array
    {
        return $this->client()
            ->post(rtrim($this->getIssuer(), '/').'/oidc/v1.0/token', [
                'grant_type' => 'authorization_code',
                'code' => $code,
                'code_verifier' => $codeVerifier,
                'redirect_uri' => $this->redirectUri,
                'client_id' => $this->clientId,
            ])
            ->json();
    }

    public function setTenantCode(string $tenantCode): void
    {
        $this->pxUserTenant = $tenantCode;
    }

    public function setDomainCode(string $domainCode): void
    {
        $this->pxUserDomain = $domainCode;
    }

    public function setClientId(string $clientId): void
    {
        $this->clientId = $clientId;
    }

    public function setRedirectUri(string $redirectUri): void
    {
        $this->redirectUri = $redirectUri;
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

    private function getIssuer(): string
    {
        $tenant = $this->pxUserTenant ?? config('px-user.tenant');
        $domain = $this->pxUserDomain ?? config('px-user.domain');

        return "$tenant:$domain";
    }
}
