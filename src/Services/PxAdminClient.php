<?php

namespace mindtwo\PxUserLaravel\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class PxAdminClient
{
    /**
     * The stage the app runs in.
     *
     * @var ?string
     */
    private $stage = null;

    /**
     * PX User tenant setting.
     *
     * @var ?string
     */
    private $tenant = null;

    /**
     * PX User domain setting.
     *
     * @var ?string
     */
    private $domain = null;

    /**
     * Machine-to-machine credentials used for communication between backend
     * and PX User API.
     *
     * @var ?string
     */
    private $m2mCredentials = null;

    /**
     * Urls for available environments.
     *
     * @var string[]
     */
    protected $uris = [
        'testing' => 'https://user.api.pl-x.cloud/v1/',
        'prod' => 'https://user.api.pl-x.cloud/v1/',
        'dev' => 'https://user.api.dev.pl-x.cloud/v1/',
        'preprod' => 'https://user.api.preprod.pl-x.cloud/v1/',
    ];

    public function __construct(
        private array $config = [],
    ) {
        $this->stage = $config['stage'] ?? 'prod';

        $this->setCredentials(
            ($config['tenant'] ?? 'plx'),
            ($config['domain'] ?? null),
            ($config['m2m_credentials'] ?? null)
        );
    }

    public function request(array $headers = [])
    {
        $context = $this->getContext();
        $uri = $this->getUri();

        $reqHeaders = array_merge([
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'X-M2M-Authorization' => $this->m2mCredentials,
            'X-M2M-User-Context' => $context,
        ], $headers);

        return Http::withHeaders($reqHeaders)->baseUrl($uri);
    }

    public function setCredentials($tenant, $domain, $m2mCredentials)
    {
        $this->tenant = $tenant;
        $this->domain = $domain;
        $this->m2mCredentials = $m2mCredentials;

        return $this;
    }

    public function scope($tenant, $domain, callable $callback)
    {
        $defaultTenant = $this->tenant;
        $defaultDomain = $this->domain;

        $this->setCredentials($tenant, $domain, $this->m2mCredentials);

        $callback($this);

        $this->tenant = $defaultTenant;
        $this->domain = $defaultDomain;

        return $this;
    }

    /**
     * Login user using username and password.
     *
     * @return array|null
     */
    public function login($username, $password)
    {
        try {
            $response = $this->request()->post('login', [
                'username' => $username,
                'password' => $password,
                'tenant_code' => $this->tenant,
                'domain_code' => $this->domain,
            ])->throw();
        } catch (Throwable $e) {
            Log::error('Failed refresh token for url: ');
            Log::error($this->getUri());
            Log::error($e->getMessage());

            return null;
        }

        // Check if status is 200
        if ($response->getStatusCode() === 200) {
            $body = $response->getBody();
            $responseData = optional(json_decode((string) $body))->response;

            return [
                'access_token' => $responseData->access_token,
                'access_token_expiration_utc' => $responseData->access_token_expiration_utc,
                'refresh_token' => $responseData->refresh_token,
                'refresh_token_expiration_utc' => $responseData->refresh_token_expiration_utc,
            ];
        }

        return null;
    }

    /**
     * @return array|null
     */
    public function refreshToken($refresh_token)
    {
        try {
            $response = $this->request([
                'Authorization' => "Bearer {$refresh_token}",
            ])->get('refresh-tokens')->throw();
        } catch (Throwable $e) {
            Log::error('Failed refresh token for url: ');
            Log::error($this->getUri());
            Log::error($e->getMessage());

            return null;
        }

        // Check if status is 200
        if ($response->getStatusCode() === 200) {
            $body = $response->getBody();
            $responseData = optional(json_decode((string) $body))->response;

            return [
                'access_token' => $responseData->access_token,
                'access_token_expiration_utc' => $responseData->access_token_expiration_utc,
                'refresh_token' => $responseData->refresh_token,
                'refresh_token_expiration_utc' => $responseData->refresh_token_expiration_utc,
            ];
        }

        return null;
    }

    /**
     * Get px-user uri.
     *
     * @return string
     */
    public function getUri(): string
    {
        return isset($this->stage) ? $this->uris[$this->stage] : $this->uris['prod'];
    }

    /**
     * Get px-user context.
     *
     * @return string
     */
    public function getContext(): string
    {
        return "{$this->tenant}:{$this->domain}";
    }
}
