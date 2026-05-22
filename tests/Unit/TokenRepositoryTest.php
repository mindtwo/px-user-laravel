<?php

use Illuminate\Support\Carbon;
use mindtwo\PxUserLaravel\Models\PxUserToken;
use mindtwo\PxUserLaravel\Services\PxUserTokens;
use mindtwo\PxUserLaravel\Tests\Fake\User;

beforeEach(function () {
    config(['px-user.user_model' => User::class]);
    config(['px-user.px_user_id' => 'px_user_id']);
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

test('save persists token data for the authenticatable', function () {
    $user = User::factory()->create(['px_user_id' => 'user-123']);
    $tokens = app(PxUserTokens::class);

    $token = $tokens->save($user, makeTokenData(['access_token' => 'abc']));

    expect($token)->toBeInstanceOf(PxUserToken::class)
        ->and($token->authenticatable_id)->toBe($user->getAuthIdentifier())
        ->and($token->authenticatable_type)->toBe(get_class($user))
        ->and($token->token('access_token'))->toBe('abc')
        ->and($token->valid_until)->toBeInstanceOf(Carbon::class);
});

test('accessToken returns the latest saved access token', function () {
    $user = User::factory()->create(['px_user_id' => 'user-123']);
    $tokens = app(PxUserTokens::class);

    $tokens->save($user, makeTokenData(['access_token' => 'latest-token']));

    expect($tokens->accessToken($user))->toBe('latest-token');
});

test('accessToken throws when no token exists', function () {
    $user = User::factory()->create(['px_user_id' => 'user-123']);
    $tokens = app(PxUserTokens::class);

    expect(fn () => $tokens->accessToken($user))->toThrow(RuntimeException::class);
});

test('isCurrentTokenValid returns true for fresh token', function () {
    $user = User::factory()->create(['px_user_id' => 'user-123']);
    $tokens = app(PxUserTokens::class);

    $tokens->save($user, makeTokenData());

    expect($tokens->isCurrentTokenValid($user))->toBeTrue();
});

test('isCurrentTokenValid returns false when no token exists', function () {
    $user = User::factory()->create(['px_user_id' => 'user-123']);
    $tokens = app(PxUserTokens::class);

    expect($tokens->isCurrentTokenValid($user))->toBeFalse();
});

test('invalidate marks existing tokens as expired', function () {
    $user = User::factory()->create(['px_user_id' => 'user-123']);
    $tokens = app(PxUserTokens::class);

    $tokens->save($user, makeTokenData());

    expect($tokens->invalidate($user))->toBeTrue();

    $stored = PxUserToken::query()->forAuthenticatable($user)->valid()->first();
    expect($stored)->toBeNull();
});

test('save invalidates previous tokens before storing a new one', function () {
    $user = User::factory()->create(['px_user_id' => 'user-123']);
    $tokens = app(PxUserTokens::class);

    $tokens->save($user, makeTokenData(['access_token' => 'first']));
    $tokens->save($user, makeTokenData(['access_token' => 'second']));

    $validTokens = PxUserToken::query()->forAuthenticatable($user)->valid()->get();
    expect($validTokens)->toHaveCount(1)
        ->and($validTokens->first()->token('access_token'))->toBe('second');
});

test('refreshToken returns the stored refresh token', function () {
    $user = User::factory()->create(['px_user_id' => 'user-123']);
    $tokens = app(PxUserTokens::class);

    $tokens->save($user, makeTokenData(['refresh_token' => 'refresh-abc']));

    expect($tokens->refreshToken($user))->toBe('refresh-abc');
});

test('canRefreshCurrentToken reflects refresh token presence', function () {
    $user = User::factory()->create(['px_user_id' => 'user-123']);
    $tokens = app(PxUserTokens::class);

    $tokens->save($user, makeTokenData(['refresh_token' => 'refresh-abc']));

    expect($tokens->canRefreshCurrentToken($user))->toBeTrue();
});
