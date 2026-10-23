<?php

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Laravel\Scout\Builder;
use mindtwo\PxUserLaravel\Scout\PxUserEngine;
use mindtwo\PxUserLaravel\Services\PxUserTokens;
use mindtwo\PxUserLaravel\Tests\Fake\User;

beforeEach(function () {
    config(['px-user.user_model' => User::class]);
    config(['px-user.px_user_id' => 'px_user_id']);
    config(['px-user.domain' => 'test-domain']);
    config(['px-user.tenant' => 'test-tenant']);
    config(['px-user.apiClient.baseUrl' => 'https://api.example.com']);
});

test('search sends the configured product code', function () {
    config(['px-user.product_code' => 'crm']);
    $user = User::factory()->create(['px_user_id' => 'user-123']);
    (new PxUserTokens)->save($user, [
        'access_token' => 'access-token',
        'access_token_expiration_utc' => now()->addHour()->toIso8601String(),
    ]);
    Auth::login($user);
    Http::fake(['*/v1/users*' => Http::response(['response' => ['data' => [['id' => 'user-123']]]])]);

    $results = (new PxUserEngine)->search(new Builder(new User, 'jane'));

    expect($results['total'])->toBe(1);
    Http::assertSent(fn (Request $request) => $request->hasHeader('X-Context-Product-Code', 'crm'));
});
