<?php

namespace mindtwo\PxUserLaravel\Actions;

use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\App;
use mindtwo\PxUserLaravel\Http\PxAdminClient;

class PxUserRefreshTokenAction
{
    /**
     * Refresh px user access tokens by refresh token.
     */
    public function __invoke(string $refreshToken): array
    {
        // TODO use access token helper

        try {
            $pxAdminClient = App::make(PxAdminClient::class);

            $refreshed = $pxAdminClient->refreshToken($refreshToken);
        } catch (\Throwable $th) {
            throw new HttpResponseException(response()->json([
                'message' => __('refresh.failed'),
            ], 401));
        }

        if ($refreshed === null) {
            throw new HttpResponseException(response()->json([
                'message' => __('refresh.failed'),
            ], 401));
        }

        return $refreshed;

    }
}
