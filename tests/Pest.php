<?php

use Illuminate\Support\Facades\Http;
use mindtwo\PxUserLaravel\Tests\TestCase;

uses(TestCase::class)->in(__DIR__)
    ->beforeEach(function () {
        // Set up any necessary preconditions for the tests
        Http::preventStrayRequests();
    });

function makeTokenData(array $overrides = []): array
{
    return array_merge([
        'access_token' => 'access-token-'.uniqid(),
        'access_token_expiration_utc' => now()->addHour()->toIso8601String(),
        'refresh_token' => 'refresh-token-'.uniqid(),
        'refresh_token_expiration_utc' => now()->addWeek()->toIso8601String(),
    ], $overrides);
}
