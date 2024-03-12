<?php

namespace mindtwo\PxUserLaravel\Http\Client;

use mindtwo\PxUserLaravel\Facades\PxUserSession;
use Throwable;

class PxUserClient extends PxClient
{
    public const USER = 'user';

    public const USER_WITH_PERMISSIONS = 'user-with-permissions';

    public const USER_DETAILS = ['post', 'users/details'];

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

    /**
     * Get user data from PX-User API.
     *
     *
     * @throws Throwable
     */
    // public function getUserDetails(string $access_token, array $px_user_ids): ?array
    // {
    //     if (count($px_user_ids) < 0) {
    //         return null;
    //     }

    //     // check token expiration
    //     try {
    //         //code...
    //         /** @var \Illuminate\Http\Client\Response $response */
    //         $response = $this->request([
    //             'Authorization' => "Bearer {$access_token}",
    //             'X-Context-Tenant-Code' => $this->tenant,
    //             'X-Context-Domain-Code' => $this->domain,
    //         ])->post('users/details', [
    //             'user_ids' => $px_user_ids,
    //         ])->throw();
    //     } catch (\Throwable $th) {
    //         Log::error("Failed to get user details for url: {$this->getUri()}", [
    //             'message' => $th->getMessage(),
    //             'th' => $th,
    //             'url' => $this->getUri(),
    //             'response' => $th instanceof RequestException ? $th->response->json() : null,
    //         ]);

    //         return null;
    //     }

    //     // Check if status is 200
    //     if ($response->status() === 200) {
    //         $body = $response->body();

    //         $responseData = json_decode((string) $body, true);

    //         $data = $responseData['response'];
    //         if ($data) {
    //             return $data;
    //         }
    //     }

    //     return null;
    // }
}
