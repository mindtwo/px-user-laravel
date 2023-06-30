<?php

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use mindtwo\PxUserLaravel\Http\PxAdminClient;

beforeEach(function () {
    Auth::logout();

    Cache::flush();

    fakePxUserApi();
});

it('checks if px admin client can be instantiated', function () {
    expect(app()->make(PxAdminClient::class))->toBeInstanceOf(PxAdminClient::class);
});

it('checks if px admin client can be instantiated with valid m2m', function () {
    Config::set('px-user.m2m_credentials', null);

    try {
        app()->make(PxAdminClient::class);
    } catch (\Throwable $th) {
        expect($th)->toBeInstanceOf(\TypeError::class);
    }
});

it('checks if px admin client can get user data', function () {
    $fakeUuid = '94549d0a-4386-4ba7-ae48-f9247429e5c6';

    $pxAdmin = app()->make(PxAdminClient::class);

    $response = $pxAdmin->user($fakeUuid);

    expect($response['id'])->toEqual('94549d0a-4386-4ba7-ae48-f9247429e5c6');
});

it('checks if px admin client throws on 429', function () {
    $fakeUuid = '94549d0a-4386-4ba7-ae48-f9247429e5c6';

    $pxAdmin = app()->make(PxAdminClient::class);

    $pxAdmin->user($fakeUuid);
    $pxAdmin->user($fakeUuid);

    expect(fn () => $pxAdmin->user($fakeUuid))->toThrow(RequestException::class);
});

it('checks if px admin client throws only on 429 and returns null', function () {
    $fakeUuid = '94549d0a-4386-4ba7-ae48-f9247429e5c6';

    $pxAdmin = app()->make(PxAdminClient::class);

    $pxAdmin->user($fakeUuid);
    $pxAdmin->user($fakeUuid);

    expect(fn () => $pxAdmin->user($fakeUuid))->toThrow(RequestException::class);

    expect($pxAdmin->user($fakeUuid))->toBeNull();
});
