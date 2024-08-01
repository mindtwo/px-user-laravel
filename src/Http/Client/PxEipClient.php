<?php

namespace mindtwo\PxUserLaravel\Http\Client;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

class PxEipClient extends PxClient
{
    private string $userToken;
    public function __construct(
        string $userToken,
        ?string $tenantCode = null,
        ?string $domainCode = null,
        ?string $baseUrl = null,
        string $version = 'v2',
    ) {
        parent::__construct(
            tenantCode: $tenantCode,
            domainCode: $domainCode,
            baseUrl: $baseUrl,
            version: $version,
        );
        $this->userToken = $userToken;
    }

    /**
     * Base request for eip module in px user API.
     */
    public function client(array $headers = []): PendingRequest
    {
        return Http::withHeaders($this->headers($headers))
            ->withToken($this->userToken)
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
}
