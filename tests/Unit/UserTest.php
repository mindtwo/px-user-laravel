<?php

use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Session;
use mindtwo\PxUserLaravel\Actions\PxUserDataRefreshAction;
use mindtwo\PxUserLaravel\Actions\PxUserLoginAction;
use mindtwo\PxUserLaravel\Facades\AccessTokenHelper;
use mindtwo\PxUserLaravel\Http\PxUserClient;
use mindtwo\PxUserLaravel\Tests\Fake\User as FakeUser;

uses(RefreshDatabase::class);

beforeEach(function () {
    Auth::logout();

    Cache::flush();

    fakePxUserApi();
});

it('checks if the user data gets refreshed if access_token is valid', function () {
    $this->actingAs(FakeUser::factory()->create());
    AccessTokenHelper::saveTokenData([
        'access_token' => 'token-123',
        'access_token_expiration_utc' => Carbon::now()->addHours(2),
        'refresh_token' => 'refresh-token-123',
        'refresh_token_expiration_utc' => Carbon::now()->addHours(12),
    ]);

    $pxUserClient = new PxUserClient(config('px-user'));

    $result = (new PxUserDataRefreshAction($pxUserClient))->execute();

    // expect the id to be the faked one
    expect($result['id'])->toEqual('94549d0a-4386-4ba7-ae48-f9247429e5c6');

    // expect session to not have changed
    expect(AccessTokenHelper::get('access_token'))->toEqual('token-123');
});

it('checks if we can refresh our token', function () {
    $this->actingAs(FakeUser::factory()->create());
    AccessTokenHelper::saveTokenData([
        'access_token' => 'token-123',
        'access_token_expiration_utc' => Carbon::now()->subMinute(),
        'refresh_token' => 'refresh-token-123',
        'refresh_token_expiration_utc' => Carbon::now()->addHours(10),
    ]);

    $pxUserClient = new PxUserClient(config('px-user'));

    $result = (new PxUserDataRefreshAction($pxUserClient))->execute();

    // expect the id to be the faked one
    expect($result['id'])->toEqual('94549d0a-4386-4ba7-ae48-f9247429e5c6');
    expect(AccessTokenHelper::get('access_token'))->toEqual('token-abc');
});

it('checks if we return null if our tokens both expired', function () {
    $this->actingAs(FakeUser::factory()->create());
    AccessTokenHelper::saveTokenData([
        'access_token' => 'token-123',
        'access_token_expiration_utc' => Carbon::now()->subMinute(),
        'refresh_token' => 'refresh-token-123',
        'refresh_token_expiration_utc' => Carbon::now()->subMinute(),
    ]);

    $pxUserClient = new PxUserClient(config('px-user'));

    $result = (new PxUserDataRefreshAction($pxUserClient))->execute();

    expect($result)->toBeNull();
});

it('checks if the user can log-in with valid token data', function () {
    $tokenData = [
        'access_token' => 'token-123',
        'access_token_expiration_utc' => Carbon::now()->addHours(2)->toString(),
        'refresh_token' => 'refresh-token-123',
        'refresh_token_expiration_utc' => Carbon::now()->addHours(12)->toString(),
    ];

    // AccessTokenHelper::flush();

    config([
        'px-user.user_model' => FakeUser::class,
    ]);

    $pxUserClient = new PxUserClient(config('px-user'));

    $result = (new PxUserLoginAction($pxUserClient))->execute($tokenData);

    expect($result)->toBeTrue();
    expect(Auth::user()->px_user_id)->toEqual('94549d0a-4386-4ba7-ae48-f9247429e5c6');

    Auth::logout();
    Session::invalidate();
});
