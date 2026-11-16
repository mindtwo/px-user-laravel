<?php

namespace mindtwo\PxUserLaravel\Testing\Clients;

use mindtwo\PxUserLaravel\Http\Client\PxUserOidcClient;
use mindtwo\PxUserLaravel\Testing\FakePxUserState;
use mindtwo\PxUserLaravel\Testing\PxUserEndpoint;

/**
 * Exchanges OIDC codes for tokens of FakePxUser's next login user, without HTTP requests.
 *
 * @internal Bound in place of PxUserOidcClient by FakePxUser::fake().
 */
class FakePxUserOidcClient extends PxUserOidcClient
{
    /**
     * The client settings, recorded with each exchange.
     *
     * @var array<string, string>
     */
    private array $settings = [];

    public function __construct(private readonly FakePxUserState $state) {}

    public function exchangeToken(string $code, string $codeVerifier): array
    {
        $this->state->call(PxUserEndpoint::OidcToken, [
            ...$this->settings,
            'code' => $code,
            'code_verifier' => $codeVerifier,
        ]);

        $tokens = $this->state->issueTokens($this->state->nextLoginId());

        return [
            'access_token' => $tokens['access_token'],
            'refresh_token' => $tokens['refresh_token'],
            'token_type' => 'Bearer',
            'expires_in' => 3600,
            'refresh_token_expires_in' => 604800,
            'id_token' => 'fake.id.token',
            'scope' => 'openid',
        ];
    }

    public function setTenantCode(string $tenantCode): void
    {
        $this->settings['tenant_code'] = $tenantCode;
        parent::setTenantCode($tenantCode);
    }

    public function setDomainCode(string $domainCode): void
    {
        $this->settings['domain_code'] = $domainCode;
        parent::setDomainCode($domainCode);
    }

    public function setClientId(string $clientId): void
    {
        $this->settings['client_id'] = $clientId;
        parent::setClientId($clientId);
    }

    public function setRedirectUri(string $redirectUri): void
    {
        $this->settings['redirect_uri'] = $redirectUri;
        parent::setRedirectUri($redirectUri);
    }
}
