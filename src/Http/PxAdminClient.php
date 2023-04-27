<?php

namespace mindtwo\PxUserLaravel\Http;

use Illuminate\Support\Facades\Log;
use Throwable;

class PxAdminClient extends PxClient
{
    /**
     * Machine-to-machine credentials used for communication between backend
     * and PX User API.
     *
     * @var ?string
     */
    protected $m2mCredentials = null;

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

    /**
     * Update M2M Credentials for a request.
     *
     * @param string $m2mCredentials
     * @return self
     */
    public function setM2M(string $m2mCredentials): self
    {
        $this->m2mCredentials = $m2mCredentials;

        return $this;
    }

    public function scope($tenant, $domain, callable $callback)
    {
        $defaultTenant = $this->tenant;
        $defaultDomain = $this->domain;

        $this->setCredentials($tenant, $domain);

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
            Log::error('Failed to login user for url: ');
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
     * Get request headers.
     *
     * @param array $headers
     * @return array
     */
    public function headers(array $headers = []): array
    {
        if (!isset($headers['X-M2M-Authorization'])) {
            $headers['X-M2M-Authorization'] = $this->m2mCredentials;
        }

        return parent::headers($headers);
    }

}
