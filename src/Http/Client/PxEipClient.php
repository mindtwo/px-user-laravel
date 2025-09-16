<?php

namespace mindtwo\PxUserLaravel\Http\Client;

use mindtwo\PxApiClients\Base\Traits\AuthorizedUserContext;

class PxEipClient extends PxUserClient
{
    use AuthorizedUserContext;

    public function __construct()
    {
        parent::__construct(
            version: 'v2'
        );
    }
}
