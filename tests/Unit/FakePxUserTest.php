<?php

use Illuminate\Support\Facades\Cache;
use mindtwo\PxUserLaravel\PxUser;
use mindtwo\PxUserLaravel\Testing\FakePxUser;
use mindtwo\PxUserLaravel\Tests\Fake\User;

beforeEach(function () {
    config(['px-user.user_model' => User::class]);
    config(['px-user.px_user_id' => 'px_user_id']);
    config(['px-user.domain' => 'test-domain']);
    config(['px-user.tenant' => 'test-tenant']);
    config(['px-user.px_user_cache_time' => 120]);

    Cache::flush();
});

test('fake replaces the PxUser service in container', function () {
    FakePxUser::fake();

    expect(resolve(PxUser::class))->toBeInstanceOf(FakePxUser::class);
});

test('fake can login user', function () {
    $user = FakePxUser::actAs(User::factory()->create(), [
        'firstname' => 'Actor',
        'lastname' => 'As',
    ]);

    expect(auth()->user()->firstname)->toBe('Actor')
        ->and(auth()->user()->lastname)->toBe('As');

});
