<?php

namespace mindtwo\PxUserLaravel\Http;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Log;
use mindtwo\PxUserLaravel\Facades\PxUser;
use Throwable;

class PxUserClient extends PxClient
{
    public function __construct(
        private array $config = [],
    ) {
        $this->stage = $config['stage'] ?? 'prod';

        $this->setCredentials(
            ($config['tenant'] ?? null),
            ($config['domain'] ?? null),
        );
    }

    /**
     * Get user data from PX-User API.
     *
     *
     * @throws Throwable
     */
    public function getUserData(string $access_token, bool $withPermissions = false): ?array
    {
        if (PxUser::isFaking()) {
            return PxUser::fakeUserData(auth()->user()?->{config('px-user.px_user_id')});
        }

        // check token expiration
        try {
            $response = $this->request([
                'Authorization' => "Bearer {$access_token}",
            ])
            ->get($withPermissions ? 'user-with-permissions' : 'user')
            ->throw();
        } catch (Throwable $e) {
            if (config('px-user.debug')) {
                Log::error("Failed to login user for url: {$this->getUri()}", [
                    'message' => $e->getMessage(),
                    'url' => $this->getUri(),
                    'path' => $withPermissions ? 'user-with-permissions' : 'user',
                ]);
            }

            throw $e;
        }

        // Check if status is 200
        if ($response->status() === 200) {
            $body = $response->body();
            $responseData = json_decode((string) $body, true);

            // parse response body and return stdClass Object
            $userData = optional($responseData['response'])['user'];

            if ($userData) {
                return $userData;
            }
        }

        return null;
    }

    /**
     * Get user data from PX-User API.
     *
     *
     * @throws Throwable
     */
    public function getUserDetails(string $access_token, array $px_user_ids): ?array
    {
        if (count($px_user_ids) < 0) {
            return null;
        }

        // check token expiration
        try {
            //code...
            /** @var \Illuminate\Http\Client\Response $response */
            $response = $this->request([
                'Authorization' => "Bearer {$access_token}",
                'X-Context-Tenant-Code' => $this->tenant,
                'X-Context-Domain-Code' => $this->domain,
            ])->post('users/details', [
                'user_ids' => $px_user_ids,
            ])->throw();
        } catch (\Throwable $th) {
            Log::error("Failed to get user details for url: {$this->getUri()}", [
                'message' => $th->getMessage(),
                'th' => $th,
                'url' => $this->getUri(),
                'response' => $th instanceof RequestException ? $th->response->json() : null,
            ]);

            return null;
        }

        // Check if status is 200
        if ($response->status() === 200) {
            $body = $response->body();

            $responseData = json_decode((string) $body, true);

            $data = $responseData['response'];
            if ($data) {
                return $data;
            }
        }

        return null;
    }
}
