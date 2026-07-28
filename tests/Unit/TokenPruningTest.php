<?php

use mindtwo\PxUserLaravel\Models\PxUserToken;
use mindtwo\PxUserLaravel\Tests\Fake\User;

beforeEach(function () {
    config(['px-user.user_model' => User::class]);
    config(['px-user.px_user_id' => 'px_user_id']);
    config(['px-user.token_retention_days' => 30]);
});

function makeToken(User $user, ?Carbon\Carbon $validUntil): PxUserToken
{
    return PxUserToken::query()->create([
        'authenticatable_type' => get_class($user),
        'authenticatable_id' => $user->getAuthIdentifier(),
        'token_data' => ['access_token' => 'access-'.uniqid()],
        'valid_until' => $validUntil,
    ]);
}

test('prunable selects tokens expired longer than the retention period', function () {
    $user = User::factory()->create(['px_user_id' => 'user-123']);

    $stale = makeToken($user, now()->subDays(31));
    $recentlyExpired = makeToken($user, now()->subDay());
    $active = makeToken($user, now()->addHour());
    $neverExpires = makeToken($user, null);

    $prunableIds = (new PxUserToken)->prunable()->pluck('id');

    expect($prunableIds->all())->toBe([$stale->id])
        ->and($prunableIds)->not->toContain($recentlyExpired->id)
        ->and($prunableIds)->not->toContain($active->id)
        ->and($prunableIds)->not->toContain($neverExpires->id);
});

test('model:prune deletes tokens beyond the retention period', function () {
    $user = User::factory()->create(['px_user_id' => 'user-123']);

    $stale = makeToken($user, now()->subDays(31));
    $active = makeToken($user, now()->addHour());

    $this->artisan('model:prune', ['--model' => [PxUserToken::class]])->assertSuccessful();

    expect(PxUserToken::query()->find($stale->id))->toBeNull()
        ->and(PxUserToken::query()->find($active->id))->not->toBeNull();
});

test('retention period is configurable', function () {
    $user = User::factory()->create(['px_user_id' => 'user-123']);
    config(['px-user.token_retention_days' => 1]);

    $token = makeToken($user, now()->subDays(2));

    expect((new PxUserToken)->prunable()->pluck('id')->all())->toBe([$token->id]);
});
