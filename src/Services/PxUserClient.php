<?php

namespace mindtwo\PxUserLaravel\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class PxUserClient
{
    /**
     * The stage the app runs in
     *
     * @var ?string
     */
    private $stage = null;

    /**
     * PX User tenant setting
     *
     * @var ?string
     */
    private $tenant = null;

    /**
     * PX User domain setting
     *
     * @var ?string
     */
    private $domain = null;

    /**
     * Machine-to-machine credentials used for communication between backend
     * and PX User API
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

    public function setCredentials($tenant, $domain, $m2mCredentials)
    {
        $this->tenant = $tenant;
        $this->domain = $domain;
        $this->m2mCredentials = $m2mCredentials;

        $context = $this->getContext();
        $uri = $this->getUri();

        // create our pxUser macro
        Http::macro('pxUser', function () use ($uri, $m2mCredentials, $context) {
            return Http::withHeaders([
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'X-M2M-Authorization' => $m2mCredentials,
                'X-M2M-User-Context' => $context,
            ])->baseUrl($uri)->throw();
        });

        return $this;
    }

    /**
     * Get user data from PX-User API.
     *
     * @return array|null
     *
     * @throws Throwable
     */
    public function getUserData($access_token): ?array
    {
        // check token expiration
        try {
            $response = Http::pxUser()->withHeaders([
                'Authorization' => "Bearer {$access_token}",
            ])->get('user');
        } catch (Throwable $e) {
            Log::error('Failed login for url: ');
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
     * @return array|null
     */
    public function refreshToken($refresh_token)
    {
        try {
            $response = Http::pxUser()->withHeaders([
                'Authorization' => "Bearer {$refresh_token}",
            ])->get('refresh-tokens');
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
     * Get px-user uri
     *
     * @return string
     */
    public function getUri(): string
    {
        return isset($this->stage) ? $this->uris[$this->stage] : $this->uris['prod'];
    }

    /**
     * Get px-user context
     *
     * @return string
     */
    public function getContext(): string
    {
        return "{$this->tenant}:{$this->domain}";
    }
}
