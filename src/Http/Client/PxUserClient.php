<?php

namespace mindtwo\PxUserLaravel\Http\Client;

use Illuminate\Http\Client\PendingRequest;
use mindtwo\PxApiClients\Apis\User\PxUserApiClient;
use mindtwo\PxApiClients\Base\Traits\PxRequestContext;

class PxUserClient extends PxUserApiClient
{
    use PxRequestContext;

    public const USER = 'user';

    public const USER_WITH_PERMISSIONS = 'user-with-permissions';

    public const USER_DETAILS = 'users/details';

    public const USER_VALIDITY = 'user-with-permissions?withExtendedProducts=true';

    public function __construct(
        ?string $tenantCode = null,
        ?string $domainCode = null,
    ) {
        parent::__construct(
            stage: config('px-user.stage', 'prod'),
        );

        $tenantCode = $tenantCode ?: config('px-user.tenant_code');
        $domainCode = $domainCode ?: config('px-user.domain_code');

        if ($tenantCode) {
            $this->setOption('tenantCode', $tenantCode);
        }

        if ($domainCode) {
            $this->setOption('domainCode', $domainCode);
        }
    }

    public function clientWithHeaders(array $headers): PendingRequest
    {
        return $this->client()
            ->withHeaders($headers);
    }
}
