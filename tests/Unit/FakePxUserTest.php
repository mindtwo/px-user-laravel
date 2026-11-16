<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use mindtwo\PxUserLaravel\Events\PxUserTokenRefreshEvent;
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
    $this->actingAs($user);

    expect(auth()->user()->firstname)->toBe('Actor')
        ->and(auth()->user()->lastname)->toBe('As');

});

test('fakePxUserData seeds attributes for a non-authenticated user', function () {
    $actor = FakePxUser::actAs(User::factory()->create(['px_user_id' => 'actor-1']));
    $this->actingAs($actor);

    $recipient = User::factory()->create(['px_user_id' => 'recipient-1']);

    // Without seeding, the recipient's proxied attributes resolve to null.
    expect($recipient->email)->toBeNull();

    FakePxUser::fakePxUserData($recipient);

    $recipient = User::query()->find($recipient->getKey());

    expect($recipient->email)->toBe('recipient-1@example.com')
        ->and($recipient->firstname)->toBe('Test')
        ->and($recipient->lastname)->toBe('User')
        ->and($recipient->preferredUsername)->toBe('recipient-1');
});

test('fakePxUserData applies overrides', function () {
    $actor = FakePxUser::actAs(User::factory()->create(['px_user_id' => 'actor-1']));
    $this->actingAs($actor);

    $recipient = User::factory()->create(['px_user_id' => 'recipient-1']);

    FakePxUser::fakePxUserData($recipient, [
        'email' => 'custom@example.com',
        'firstname' => 'Custom',
    ]);

    $recipient = User::query()->find($recipient->getKey());

    expect($recipient->email)->toBe('custom@example.com')
        ->and($recipient->firstname)->toBe('Custom')
        ->and($recipient->lastname)->toBe('User');
});

test('fakePxUserData writes to the same cache key actAs uses', function () {
    $user = User::factory()->create(['px_user_id' => 'user-1']);

    FakePxUser::fakePxUserData($user, ['email' => 'seeded@example.com']);

    expect(Cache::get($user->cachedAttributeKey()))
        ->toMatchArray(['email' => 'seeded@example.com']);
});

test('fake passes the faked user data to the refresh event', function () {
    Event::fake([PxUserTokenRefreshEvent::class]);
    $user = FakePxUser::actAs(User::factory()->create(['px_user_id' => 'actor-1']), ['firstname' => 'Refreshed']);
    Http::fake(['*v1/refresh-tokens' => Http::response(['response' => makeTokenData(['access_token' => 'refreshed'])])]);
    $this->travel(2)->hours();

    expect(resolve(PxUser::class)->refreshSession($user))->toBeTrue();

    Http::assertSentCount(1);
    Event::assertDispatched(PxUserTokenRefreshEvent::class, fn (PxUserTokenRefreshEvent $event) => $event->userData->id === 'actor-1'
        && $event->userData->firstname === 'Refreshed');
});
