<?php

use Illuminate\Support\Facades\Http;
use mindtwo\PxUserLaravel\Tests\TestCase;

uses(TestCase::class)->in(__DIR__);

/**
 * Fake px user api calls.
 *
 * @return void
 */
function fakePxUserApi()
{
    $fakeUserData = [
        'id' => '94549d0a-4386-4ba7-ae48-f9247429e5c6',
        'email' => 'as@domain.tld',
        'tenant_code' => 'abc',
        'domain_code' => 'def',
        'is_enabled' => true,
        'is_confirmed' => true,
        'firstname' => 'Antonina',
        'lastname' => 'Stępień',
        'last_login_at' => '',
        'products' => [
            'prod',
        ],
        'roles' => [
            'bjtd' => [
                'standard',
            ],
        ],
        'products' => [
            'bjtd',
        ],
    ];

    $fakeTokenData = [
        'access_token' => 'token-abc',
        'access_token_lifetime_minutes' => 120,
        'access_token_expiration_utc' => \Carbon\Carbon::now()->addHours(2),
        'refresh_token' => 'refresh-token-abc',
        'refresh_token_lifetime_minutes' => 43200,
        'refresh_token_expiration_utc' => \Carbon\Carbon::now()->addHours(12),
    ];

    Http::fake([
        'https://user.api.pl-x.cloud/v1/user' => Http::response([
            'success' => true,
            'code' => 200,
            'http_code' => 200,
            'message' => 'OK',
            'response' => [
                'user' => $fakeUserData,
            ],
            'metadata' => [],
        ], 200),
    ]);

    Http::fake([
        'https://user.api.pl-x.cloud/v1/user-with-permissions' => Http::response([
            'success' => true,
            'code' => 200,
            'http_code' => 200,
            'message' => 'OK',
            'response' => [
                'user' => $fakeUserData,
            ],
            'metadata' => [],
        ], 200),
    ]);

    Http::fake([
        'https://user.api.pl-x.cloud/v1/refresh-tokens' => Http::response([
            'success' => true,
            'code' => 200,
            'http_code' => 200,
            'message' => 'OK',
            'response' => $fakeTokenData,
            'metadata' => [],
        ], 200),
    ]);

    Http::fake([
        'https://user.api.preprod.pl-x.cloud/v1/user' => Http::response([
            'success' => true,
            'code' => 200,
            'http_code' => 200,
            'message' => 'OK',
            'response' => [
                'user' => $fakeUserData,
            ],
            'metadata' => [],
        ], 200),
    ]);

    Http::fake([
        'https://user.api.preprod.pl-x.cloud/v1/user-with-permissions' => Http::response([
            'success' => true,
            'code' => 200,
            'http_code' => 200,
            'message' => 'OK',
            'response' => [
                'user' => $fakeUserData,
            ],
            'metadata' => [],
        ], 200),
    ]);

    Http::fake([
        'https://user.api.preprod.pl-x.cloud/v1/refresh-tokens' => Http::response([
            'success' => true,
            'code' => 200,
            'http_code' => 200,
            'message' => 'OK',
            'response' => $fakeTokenData,
            'metadata' => [],
        ], 200),
    ]);
}
