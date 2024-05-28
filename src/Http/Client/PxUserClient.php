<?php

namespace mindtwo\PxUserLaravel\Http\Client;

class PxUserClient extends PxClient
{
    public const USER = 'user';

    public const USER_WITH_PERMISSIONS = 'user-with-permissions';

    public const USER_DETAILS = 'users/details';

    public function __construct(
        ?string $tenantCode = null,
        ?string $domainCode = null,
        ?string $baseUrl = null,
        string $version = 'v1',
    ) {
        parent::__construct(
            tenantCode: $tenantCode,
            domainCode: $domainCode,
            baseUrl: $baseUrl,
            version: $version,
        );
    }
}
