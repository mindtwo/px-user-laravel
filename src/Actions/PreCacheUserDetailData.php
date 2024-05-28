<?php

namespace mindtwo\PxUserLaravel\Actions;

use Illuminate\Http\Client\RequestException;
use mindtwo\PxUserLaravel\Cache\UserDetailDataCache;
use mindtwo\PxUserLaravel\Facades\PxUserSession;
use mindtwo\PxUserLaravel\Http\Client\PxUserClient;

class PreCacheUserDetailData
{
    public function __invoke(
        string $tenantCode,
        string $domainCode,
        string|array $userIds
    ): void {

        if (! is_array($userIds)) {
            $userIds = [$userIds];
        }

        $client = $client = new PxUserClient(
            tenantCode: $tenantCode,
            domainCode: $domainCode
        );

        $accessTokenHelper = PxUserSession::newAccessTokenHelper(auth()->user());
        if (! $accessTokenHelper->get('access_token')) {
            return;
        }

        try {
            $response = $client->post('users/details', [
                'user_ids' => $userIds,
            ], [
                'headers' => [
                    'Authorization' => 'Bearer '.$accessTokenHelper->get('access_token'),
                ],
            ])
                ->json('response');
        } catch (\Throwable $th) {
            if (! $th instanceof RequestException || ! in_array($th->response->status(), [401, 403, 404])) {
                throw $th;
            }

            $response = [];
        }

        if (empty($response)) {
            return;
        }

        collect($response)->each(function (array $userDetailData) {
            UserDetailDataCache::initialize($userDetailData);
        });
    }
}
