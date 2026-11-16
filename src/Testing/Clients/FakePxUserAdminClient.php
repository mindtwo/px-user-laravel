<?php

namespace mindtwo\PxUserLaravel\Testing\Clients;

use mindtwo\PxUserLaravel\DataTransfer\PxUserData;
use mindtwo\PxUserLaravel\Http\Client\PxUserAdminClient;
use mindtwo\PxUserLaravel\Testing\FakePxUserState;
use mindtwo\PxUserLaravel\Testing\PxUserEndpoint;

/**
 * Answers the M2M user lookup from FakePxUser's users, without HTTP requests.
 *
 * @internal Bound in place of PxUserAdminClient by FakePxUser::fake().
 */
class FakePxUserAdminClient extends PxUserAdminClient
{
    public function __construct(private readonly FakePxUserState $state) {}

    public function getUser(string $userId, string $tenantCode, string $domainCode): ?PxUserData
    {
        $this->state->call(PxUserEndpoint::AdminUser, [
            'id' => $userId,
            'tenant_code' => $tenantCode,
            'domain_code' => $domainCode,
            'm2m_credentials' => $this->m2mCredentials ?? config('px-user.m2m_credentials'),
        ]);

        $user = $this->state->find($userId)
            ?? throw FakePxUserState::requestException(404, 'User not found');

        return PxUserData::from($user);
    }
}
