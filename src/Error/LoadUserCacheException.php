<?php

namespace mindtwo\PxUserLaravel\Error;

use Exception;

class LoadUserCacheException extends Exception
{
    public string $reason;

    public int $responseStatusCode;

    public function __construct(string $reason, int $responseStatusCode)
    {
        parent::__construct('Invalid access token');

        $this->reason = $reason;
        $this->responseStatusCode = $responseStatusCode;
    }
}
