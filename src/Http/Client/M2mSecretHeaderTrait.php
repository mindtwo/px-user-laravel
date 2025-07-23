<?php

namespace mindtwo\PxUserLaravel\Http\Client;

use mindtwo\PxApiClients\Attribute\Option;

trait M2mSecretHeaderTrait
{
    /**
     * The m2m secret for the PxEmployeeAdminClient.
     */
    #[Option(configKey: 'px-user.m2m_credentials')]
    protected string $m2mSecret;

    protected function addM2mSecretHeaderTraitHeaders(): array
    {
        return array_filter([
            'x-m2m-authorization' => $this->m2mSecret,
        ]);
    }
}
