<?php

namespace mindtwo\PxUserLaravel\Http\Client;

use mindtwo\LaravelDecorator\Interfaces\Decoratable;
use mindtwo\LaravelDecorator\Traits\HasDecorator;
use Throwable;

class PxAdminClient extends PxClient implements Decoratable
{

    use HasDecorator;

    /**
     * Machine-to-machine credentials used for communication between backend
     * and PX User API.
     *
     * @var ?string
     */
    protected $m2mCredentials = null;

    public function __construct(
        ?string $tenantCode = null,
        ?string $domainCode = null,
        ?string $baseUrl = null,
        string $version = 'v1'
    ) {
        parent::__construct($tenantCode, $domainCode, $baseUrl, $version);

        $this->m2mCredentials = config('px-user.m2m_credentials');
    }

    public function setOptions(?string $tenantCode = null, ?string $domainCode = null, ?string $m2mToken = null): self
    {
        $this->m2mCredentials = $m2mToken ?? $this->m2mCredentials ?? config('px-user.m2m_credentials');

        return $this->setCredentials($tenantCode, $domainCode);
    }

    /**
     * Get request headers.
     */
    protected function headers(array $headers = []): array
    {
        $context = "{$this->tenantCode}:{$this->domainCode}";

        if (! isset($headers['X-M2M-Authorization'])) {
            $headers['X-M2M-Authorization'] = $this->m2mCredentials;
        }

        return array_merge([
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'X-M2M-User-Context' => $context,
        ], $headers);
    }

    public function validateToken(?string $token): bool
    {
        if (! $token) {
            return false;
        }

        try {
            $response = $this->get("validate-token/$token")->throw();
        } catch (Throwable $e) {
            if (config('px-user.debug')) {
                // Log::error("Failed to login user for url: {$this->getUri()}", [
                //     'message' => $e->getMessage(),
                //     'url' => $this->baseUrl,
                // ]);
            }

            return null;
        }

        return $response->ok();
    }
}
