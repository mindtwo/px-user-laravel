<?php

namespace mindtwo\PxUserLaravel\Actions;

use mindtwo\PxUserLaravel\Actions\PxUserLoginAction;
use mindtwo\PxUserLaravel\Http\PxAdminClient;
use mindtwo\PxUserLaravel\Http\PxUserClient;

class PxUserDirectLoginAction
{
    public function __construct(
        protected PxAdminClient $pxAdminClient,
    ) {
    }

    /**
     * Use token data received from login widget to login user in backend
     *
     * @param  null|string  $username
     * @param  null|string  $password
     * @param  null|string  $guard
     * @return bool
     *
     * @throws Exception
     */
    public function execute(?string $username, ?string $password, ?string $guard = null): bool
    {
        if ($username === null) {
            throw new \Exception('Please provide a valid username', 1);
        }

        if ($password === null) {
            throw new \Exception('Please provide a valid password', 1);
        }

        try {
            $tokenData = $this->pxAdminClient->login($username, $password, $guard);
        } catch (\Throwable $e) {
            throw new \Exception('No user found.', 0, $e);
        }

        $pxUserLoginAction = new PxUserLoginAction(app()->make(PxUserClient::class));

        return $pxUserLoginAction->execute($tokenData);
    }
}
