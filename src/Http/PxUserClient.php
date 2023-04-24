<?php

namespace mindtwo\PxUserLaravel\Http;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class PxUserClient
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
        );
    }

    public function request(array $headers = [])
    {
        $context = $this->getContext();
        $uri = $this->getUri();

        $reqHeaders = array_merge([
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'X-M2M-User-Context' => $context,
        ], $headers);

        return Http::withHeaders($reqHeaders)->baseUrl($uri);
    }

    public function setCredentials($tenant, $domain)
    {
        $this->tenant = $tenant;
        $this->domain = $domain;
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
     * Get user data from PX-User API.
     *
     * @param string $access_token
     * @param bool $withPermissions
     * @return array|null
     *
     * @throws Throwable
     */
    public function getUserData(string $access_token, bool $withPermissions=false): ?array
    {
        // check token expiration
        try {
            $response = $this->request([
                'Authorization' => "Bearer {$access_token}",

            ])->get($withPermissions ? 'user-with-permissions' : 'user')->throw();
        } catch (Throwable $e) {
            Log::error('Failed login for url: ');
            Log::error($this->getUri());
            Log::error($e->getMessage());

            throw $e;
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
     * Get user data from PX-User API.
     *
     * @param string $access_token
     * @param array $px_user_ids
     * @return array|null
     *
     * @throws Throwable
     */
    public function getUserDetails(string $access_token, array $px_user_ids): ?array
    {
        if (count($px_user_ids) < 0) {
            return null;
        }

        // check token expiration
        /** @var \Illuminate\Http\Client\Response $response */
        $response = $this->request([
            'Authorization' => "Bearer {$access_token}",
            'X-Context-Tenant-Code' => $this->tenant,
            'X-Context-Domain-Code' => $this->domain,
        ])->post('users/details', [
            'user_ids' => $px_user_ids,
        ]);

        // Check if status is 200
        if ($response->status() === 200) {
            $body = $response->body();

            $responseData = json_decode((string) $body, true);

            $data = $responseData['response'];
            if ($data) {
                return $data;
            }
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
