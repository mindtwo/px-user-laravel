<?php

namespace mindtwo\PxUserLaravel\Http;

use Illuminate\Support\Facades\Log;
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
     * @param  string  $access_token
     * @param  bool  $withPermissions
     * @return array|null
     *
     * @throws Throwable
     */
    public function getUserData(string $access_token, bool $withPermissions = false): ?array
    {
        // check token expiration
        try {
            $response = $this->request([
                'Authorization' => "Bearer {$access_token}",
            ])->get($withPermissions ? 'user-with-permissions' : 'user')->throw();
        } catch (Throwable $e) {
            Log::error('Failed login for url: ');
            Log::error($this->getUri());
            Log::error($e->getMessage());

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
     * @param  string  $access_token
     * @param  array  $px_user_ids
     * @return array|null
     *
     * @throws Throwable
     */
    public function getUserDetails(string $access_token, array $px_user_ids): ?array
    {
        if (count($px_user_ids) < 0) {
            return null;
        }

        // check token expiration
        /** @var \Illuminate\Http\Client\Response $response */
        $response = $this->request([
            'Authorization' => "Bearer {$access_token}",
            'X-Context-Tenant-Code' => $this->tenant,
            'X-Context-Domain-Code' => $this->domain,
        ])->post('users/details', [
            'user_ids' => $px_user_ids,
        ]);

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
