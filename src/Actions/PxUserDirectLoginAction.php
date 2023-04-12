<?php

namespace mindtwo\PxUserLaravel\Actions;

use Illuminate\Http\Request;
use mindtwo\PxUserLaravel\Actions\PxUserLoginAction;
use mindtwo\PxUserLaravel\Services\PxUserClient;

class PxUserDirectLoginAction
{
    public function __construct(
        protected PxUserClient $pxUserClient,
        protected PxUserLoginAction $pxUserLoginAction,
    ) {
    }

    /**
     * Use token data received from login widget to login user in backend
     *
     * @param  array  $tokenData
     * @return bool
     *
     * @throws Exception
     */
    public function execute(Request $request, string $username, string $password): bool
    {
        try {
            $tokenData = $this->pxUserClient->login($username, $password);
        } catch (\Throwable $e) {
            throw new \Exception('No user found.', 0, $e);
        }

        return $this->pxUserLoginAction->execute($request, $tokenData);
    }
}
