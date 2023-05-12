<?php

namespace mindtwo\PxUserLaravel\Http;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

abstract class PxClient
{
    /**
     * The stage the app runs in.
     *
     * @var ?string
     */
    protected $stage = null;

    /**
     * PX User tenant setting.
     *
     * @var ?string
     */
    protected $tenant = null;

    /**
     * PX User domain setting.
     *
     * @var ?string
     */
    protected $domain = null;

    /**
     * PX User api version setting.
     *
     * @var ?string
     */
    protected string $version = 'v1';

    /**
     * Urls for available environments.
     *
     * @var string[]
     */
    protected $uris = [
        'testing' => 'https://user.api.pl-x.cloud',
        'prod' => 'https://user.api.pl-x.cloud',
        'dev' => 'https://user.api.dev.pl-x.cloud',
        'preprod' => 'https://user.api.preprod.pl-x.cloud',
    ];

    /**
     * Base request for px user API
     *
     * @param array $headers
     * @return PendingRequest
     */
    public function request(array $headers = []): PendingRequest
    {
        return Http::withHeaders($this->headers($headers))->baseUrl($this->getUri());
    }

    /**
     * Update credentials.
     *
     * @param string $tenant
     * @param string $domain
     * @return self
     */
    public function setCredentials(string $tenant, string $domain): self
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
     * Get request headers.
     *
     * @param array $headers
     * @return array
     */
    public function headers(array $headers = []): array
    {
        $context = $this->getContext();

        return array_merge([
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'X-M2M-User-Context' => $context,
        ], $headers);
    }

    /**
     * Get px-user uri.
     *
     * @return string
     */
    public function getUri(): string
    {
        $url = isset($this->stage) ? $this->uris[$this->stage] : $this->uris['prod'];

        return "$url/{$this->version}";
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
