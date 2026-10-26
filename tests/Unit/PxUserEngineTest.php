<?php

use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Laravel\Scout\Builder;
use mindtwo\PxUserLaravel\Scout\PxUserEngine;
use mindtwo\PxUserLaravel\Services\PxUserTokens;
use mindtwo\PxUserLaravel\Tests\Fake\SearchableUser;
use mindtwo\PxUserLaravel\Tests\Fake\User;

beforeEach(function () {
    config(['px-user.user_model' => User::class]);
    config(['px-user.px_user_id' => 'px_user_id']);
    config(['px-user.domain' => 'test-domain']);
    config(['px-user.tenant' => 'test-tenant']);
    config(['px-user.apiClient.baseUrl' => 'https://api.example.com']);

    $user = User::factory()->create(['px_user_id' => 'acting-user']);
    (new PxUserTokens)->save($user, [
        'access_token' => 'access-token',
        'access_token_expiration_utc' => now()->addHour()->toIso8601String(),
    ]);
    Auth::login($user);
});

function fakePxUserSearch(array $pxUserIds): void
{
    Http::fake(['*/v1/users*' => Http::response([
        'response' => ['data' => array_map(fn (string $id) => ['id' => $id], $pxUserIds)],
    ])]);
}

function createLocalPxUsers(string ...$pxUserIds): void
{
    foreach ($pxUserIds as $pxUserId) {
        User::factory()->create(['px_user_id' => $pxUserId]);
    }
}

test('search sends the configured product code', function () {
    config(['px-user.product_code' => 'crm']);
    createLocalPxUsers('user-123');
    fakePxUserSearch(['user-123']);

    $results = (new PxUserEngine)->search(new Builder(new User, 'jane'));

    expect($results['total'])->toBe(1);
    Http::assertSent(fn (Request $request) => $request->hasHeader('X-Context-Product-Code', 'crm'));
});

test('search returns the local users in the order px returns them', function () {
    createLocalPxUsers('a', 'b', 'c');
    fakePxUserSearch(['c', 'unknown', 'a']);

    $engine = new PxUserEngine;
    $builder = new Builder(new User, 'jane');
    $results = $engine->search($builder);

    expect($engine->map($builder, $results, new User)->pluck('px_user_id')->all())->toBe(['c', 'a'])
        ->and($engine->getTotalCount($results))->toBe(2);
});

test('search without a query skips the px api', function () {
    Http::fake();

    $results = (new PxUserEngine)->search(new Builder(new User, ''));

    expect($results['results'])->toBeEmpty()
        ->and($results['total'])->toBe(0);
    Http::assertNothingSent();
});

test('search limits the results to take but counts all local matches', function () {
    createLocalPxUsers('a', 'b', 'c');
    fakePxUserSearch(['c', 'b', 'a']);

    $results = (new PxUserEngine)->search((new Builder(new User, 'jane'))->take(2));

    expect($results['results']->pluck('px_user_id')->all())->toBe(['c', 'b'])
        ->and($results['total'])->toBe(3);
});

test('search applies query callbacks to the local users', function () {
    createLocalPxUsers('a', 'b', 'c');
    fakePxUserSearch(['c', 'b', 'a']);

    $builder = (new Builder(new User, 'jane'))->query(fn ($query) => $query->where('px_user_id', '!=', 'b'));
    $results = (new PxUserEngine)->search($builder);

    expect($results['results']->pluck('px_user_id')->all())->toBe(['c', 'a'])
        ->and($results['total'])->toBe(2);
});

test('search sends the tenant and domain from the builder wheres', function () {
    fakePxUserSearch([]);

    (new PxUserEngine)->search((new Builder(new User, 'jane'))->where('tenant', 'acme')->where('domain', 'shop'));

    Http::assertSent(fn (Request $request) => $request->hasHeader('x-context-tenant-code', 'acme')
        && $request->hasHeader('x-context-domain-code', 'shop'));
});

test('search treats a px 404 as no results', function () {
    Http::fake(['*/v1/users*' => Http::response([], 404)]);

    $results = (new PxUserEngine)->search(new Builder(new User, 'jane'));

    expect($results['results'])->toBeEmpty()
        ->and($results['total'])->toBe(0);
});

test('search rethrows other px errors', function () {
    config(['px-user.apiClient.retries' => 0]);
    Http::fake(['*/v1/users*' => Http::response([], 500)]);

    (new PxUserEngine)->search(new Builder(new User, 'jane'));
})->throws(RequestException::class);

test('paginate returns the requested page of local users in px order', function () {
    createLocalPxUsers('a', 'b', 'c');
    fakePxUserSearch(['c', 'unknown', 'a', 'b']);

    $results = (new PxUserEngine)->paginate(new Builder(new User, 'jane'), 2, 2);

    expect($results['results']->pluck('px_user_id')->all())->toBe(['b'])
        ->and($results['total'])->toBe(3);
});

test('paginate returns no results past the last page', function () {
    createLocalPxUsers('a');
    fakePxUserSearch(['a']);

    $results = (new PxUserEngine)->paginate(new Builder(new User, 'jane'), 2, 3);

    expect($results['results'])->toBeEmpty()
        ->and($results['total'])->toBe(1);
});

test('scout paginates px search results', function () {
    createLocalPxUsers('a', 'b', 'c');
    fakePxUserSearch(['c', 'a', 'b']);

    $paginator = SearchableUser::search('jane')->paginate(2, 'page', 2);

    expect($paginator)->toBeInstanceOf(LengthAwarePaginator::class)
        ->and($paginator->total())->toBe(3)
        ->and($paginator->lastPage())->toBe(2)
        ->and(collect($paginator->items())->pluck('px_user_id')->all())->toBe(['b']);
});

test('scout paginates px search results with a query callback', function () {
    createLocalPxUsers('a', 'b', 'c');
    fakePxUserSearch(['c', 'a', 'b']);

    $paginator = SearchableUser::search('jane')
        ->query(fn ($query) => $query->where('px_user_id', '!=', 'a'))
        ->paginate(1, 'page', 1);

    expect($paginator->total())->toBe(2)
        ->and(collect($paginator->items())->pluck('px_user_id')->all())->toBe(['c']);
});

test('scout simple paginates px search results', function () {
    createLocalPxUsers('a', 'b', 'c');
    fakePxUserSearch(['c', 'a', 'b']);

    $paginator = SearchableUser::search('jane')->simplePaginate(2, 'page', 1);

    expect($paginator->hasMorePages())->toBeTrue()
        ->and(collect($paginator->items())->pluck('px_user_id')->all())->toBe(['c', 'a']);
});

test('scout get, keys and cursor return local users in px order', function () {
    createLocalPxUsers('a', 'b');
    fakePxUserSearch(['b', 'a']);

    $expectedKeys = SearchableUser::query()->whereIn('px_user_id', ['b', 'a'])->pluck('id', 'px_user_id');

    expect(SearchableUser::search('jane')->get()->pluck('px_user_id')->all())->toBe(['b', 'a'])
        ->and(SearchableUser::search('jane')->keys()->all())->toBe([$expectedKeys['b'], $expectedKeys['a']])
        ->and(SearchableUser::search('jane')->cursor()->pluck('px_user_id')->all())->toBe(['b', 'a']);
});
