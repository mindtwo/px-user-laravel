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
            ($config['tenant'] ?? null),
            ($config['domain'] ?? null),
        );

        $this->setM2M(($config['m2m_credentials'] ?? null));
    }

    /**
     * Update M2M Credentials for a request.
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
     * Get user data by id.
     */
    public function user(string $userId): ?array
    {
        try {
            $response = $this->request([
                'X-Context-Tenant-Code' => $this->tenant,
                'X-Context-Domain-Code' => $this->domain,
            ])->get("user/$userId")->throw();
        } catch (Throwable $e) {
            Log::error('Failed to get user data via admin for url: ');
            Log::error($this->getUri());
            Log::error($e->getMessage());

            return null;
        }

        // Check if status is 200
        if ($response->status() === 200) {
            $body = $response->body();
            $responseData = json_decode((string) $body, true);

            // parse response body and return stdClass Object
            $userData = optional($responseData['response'])['user'];

            if ($userData) {
                return $userData;
            }
        }

        return null;
    }

    /**
     * Refresh user tokens based on given refresh token.
     *
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
     * Initiate forgot password process.
     *
     * @return array|null
     */
    public function forgotPassword(string $userId)
    {
        try {
            $user = $this->user($userId);

            $response = $this->request()->post('forgot-password-code', [
                'username' => $user['preferred_username'],
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
                'forgot_password_code' => $responseData->forgot_password_code,
                'forgot_password_code_valid_until' => $responseData->forgot_password_code_valid_until,
            ];
        }

        return null;
    }

    /**
     * Get request headers.
     */
    public function headers(array $headers = []): array
    {
        if (! isset($headers['X-M2M-Authorization'])) {
            $headers['X-M2M-Authorization'] = $this->m2mCredentials;
        }

        return parent::headers($headers);
    }
}
