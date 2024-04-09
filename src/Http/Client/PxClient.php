<?php

namespace mindtwo\PxUserLaravel\Http\Client;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PxClient
{
    protected ?string $tenantCode = null;

    protected ?string $domainCode = null;

    protected string $baseUrl;

    protected string $version = 'v1';

    public function __construct(
        ?string $tenantCode = null,
        ?string $domainCode = null,
        ?string $baseUrl = null,
        string $version = 'v1',
    ) {
        $this->tenantCode = $tenantCode ?? config('px-user.tenant');
        $this->domainCode = $domainCode ?? config('px-user.domain');

        $this->baseUrl = $baseUrl ?? config('px-user.base_url');

        $this->version = $version;
    }

    /**
     * Base request for px user API.
     */
    public function client(array $headers = []): PendingRequest
    {
        return Http::withHeaders($this->headers($headers))
            ->baseUrl(sprintf(
                '%s/%s',
                rtrim($this->baseUrl, '/'),
                $this->version,
            ))
            ->connectTimeout(config('px-user.http_request_connect_timeout', 10))
            ->retry(
                config('px-user.http_request_retries'),
                function (int $attempt, \Exception $exception) {
                    return $attempt * config('px-employee-management.http_request_retry_delay', 100);
                },
                function (\Exception $exception, PendingRequest $request) {
                    if ($exception instanceof ConnectionException) {
                        return true;
                    }

                    if ($exception instanceof RequestException) {
                        return in_array($exception->response->status(), [503, 504]);
                    }

                    return false;
                });
    }

    /**
     * Update credentials.
     */
    public function setCredentials(?string $tenantCode = null, ?string $domainCode = null): self
    {
        $this->tenantCode = $tenantCode ?? $this->tenantCode;
        $this->domainCode = $domainCode ?? $this->domainCode;

        return $this;
    }

    public function scope($tenantCode, $domainCode, callable $callback)
    {
        $defaultTenant = $this->tenantCode;
        $defaultDomain = $this->domainCode;

        $this->setCredentials($tenantCode, $domainCode);

        $callback($this);

        $this->tenantCode = $defaultTenant;
        $this->domainCode = $defaultDomain;

        return $this;
    }

    /**
     * HTTP Methods
     */

    /**
     * @throws Exception
     */
    protected function send(string $method, string $url, array $options = []): Response
    {
        $headers = $options['headers'] ?? [];

        try {
            return $this->client($headers)->send($method, $url, $options);
        } catch (\Throwable $th) {
            Log::info(sprintf('An error occured while requesting external data. (Code: %s, message: %s)', $th->getCode(), $th->getMessage()), [
                'version' => $this->version,
                'baseUrl' => $this->baseUrl,
                'path' => $url,
                'tenantCode' => $this->tenantCode,
                'domainCode' => $this->domainCode,
                'headers' => $headers,
                'error' => $th->getMessage(),
                'errorCode' => $th->getCode(),
                'response' => $th instanceof RequestException ? $th->response->json() : null,
            ]);

            if (! $th instanceof RequestException || $th->response->status() >= 500) {
                throw new \Exception($th->getMessage(), $th->getCode(), $th);
            }

            throw $th;
        }

    }

    public function get(string $path, array $options = []): Response
    {
        return $this->send('GET', $path, $options);
    }

    public function post(string $path, array $data = [], array $options = []): Response
    {
        return $this->send('POST', $path, [
            'json' => $data,
            ...$options,
        ]);
    }

    public function put(string $path, array $data = [], array $options = []): Response
    {
        return $this->send('PUT', $path, ['json' => $data, ...$options]);
    }

    public function patch(string $path, array $data = [], array $options = []): Response
    {
        return $this->send('PATCH', $path, ['json' => $data, ...$options]);
    }

    public function delete(string $path, array $data = [], array $options = []): Response
    {
        return $this->send('DELETE', $path, ['json' => $data, ...$options]);
    }

    /**
     * Get request headers.
     */
    protected function headers(array $headers = []): array
    {
        // $context = "{$this->tenantCode}:{$this->domainCode}";

        return array_merge([
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'X-Context-Tenant-Code' => $this->tenantCode,
            'X-Context-Domain-Code' => $this->domainCode,
            // 'X-M2M-User-Context' => $context,
        ], $headers);
    }
}
