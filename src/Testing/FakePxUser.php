<?php

namespace mindtwo\PxUserLaravel\Testing;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use mindtwo\PxUserLaravel\DataTransfer\PxUserData;
use mindtwo\PxUserLaravel\DataTransfer\PxUserDataWithPermissions as DataTransferPxUserDataWithPermissions;
use mindtwo\PxUserLaravel\PxUser;
use mindtwo\PxUserLaravel\Services\PxUserCachedApiService;
use mindtwo\TwoTility\ExternalApiTokens\ExternalApiTokens;
use Mockery;
use RuntimeException;

class FakePxUser extends PxUser
{
    /**
     * Cached DTO for the current test context.
     */
    private static ?PxUserDataWithPermissions $cachedDto = null;

    /**
     * Fake PX User data and authenticate as that user.
     *
     * This method sets up fake PxUser behavior and logs in with fake token data.
     *
     * @param  DataTransferPxUserDataWithPermissions|array<string, mixed>  $data  Optional overrides for fake user data
     * @return Model|null The authenticated user model
     *
     * @throws RuntimeException if not running unit tests
     */
    public static function actAs(Authenticatable $user, DataTransferPxUserDataWithPermissions|array $data = []): ?Model
    {
        self::fake();

        // Set data id to px_user_id
        $data = ! is_array($data) ? $data->toArray() : $data;
        $data['id'] = $user->getPxUserId();

        // Store overrides for this actAs call
        self::getOrCreateCachedDto($data, true);

        // Login with fake token data
        $fakeTokenData = [
            'access_token' => 'fake-access-token-'.uniqid(),
            'access_token_expiration_utc' => now()->addHour()->toIso8601String(),
            'refresh_token' => 'fake-refresh-token-'.uniqid(),
            'refresh_token_expiration_utc' => now()->addWeek()->toIso8601String(),
        ];

        [$userData, $user] = resolve(PxUser::class)->resolveByToken($fakeTokenData);

        // Store the access token in the repository
        $tokenRepository = resolve(ExternalApiTokens::class)->repository('px-user');
        $tokenRepository->save($user, $fakeTokenData);

        return $user ?: null;
    }

    /**
     * Initialize fake PX User services for testing.
     *
     * This method mocks the PxUser service so that when login() or resolveByToken()
     * is called, it automatically creates fake data instead of making API calls.
     *
     * Call this once at the start of your test to enable fake mode.
     *
     * @throws RuntimeException if not running unit tests
     */
    public static function fake(): void
    {
        // Skip if already faked
        if (app()->bound('px-user.fake.enabled')) {
            return;
        }

        if (! app()->runningUnitTests()) {
            throw new RuntimeException('FakePxUser can only be used during unit tests.');
        }

        // Mark as faked
        app()->instance('px-user.fake.enabled', true);

        // Replace PxUser service in container to intercept login
        // Bind the mock to the container
        app()->instance(PxUser::class, new self);

        // Mock the PxUserCachedApiService as well
        self::mockApiService();
    }

    /**
     * {@inheritDoc}
     */
    public function resolveByToken(array $tokenData): array
    {
        // Validate token data using real implementation
        if (! $this->validateToken($tokenData)) {
            throw new RuntimeException('Invalid token data provided.');
        }

        // Get or create the cached DTO
        $fakeDto = self::getOrCreateCachedDto();

        // Retrieve or create user using real implementation
        $user = $this->retrieve($fakeDto);

        // Return fake data and retrieved user
        return [$fakeDto, $user];
    }

    /**
     * Mock the PxUserCachedApiService to return fake data.
     */
    protected static function mockApiService(): void
    {
        $mock = Mockery::mock(PxUserCachedApiService::class);

        // Mock methods to return cached DTO
        $mock->shouldReceive('getUser')
            ->andReturnUsing(fn () => PxUserData::fromExtendedData(self::getOrCreateCachedDto()));

        $mock->shouldReceive('getUserWithPermissions')
            ->andReturnUsing(fn () => self::getOrCreateCachedDto());

        $mock->shouldReceive('getUsersDetails')
            ->andReturnUsing(fn () => PxUserData::fromExtendedData(self::getOrCreateCachedDto()));

        // Bind the mock to the container
        app()->instance(PxUserCachedApiService::class, $mock);
    }

    /**
     * Get or create the cached DTO (static version for mockApiService).
     */
    private static function getOrCreateCachedDto(DataTransferPxUserDataWithPermissions|array $data = [], bool $override = false): PxUserDataWithPermissions
    {
        // Create fresh fake DTO
        if (is_null(self::$cachedDto) || $override) {
            self::$cachedDto = PxUserDataWithPermissions::fake($data);

            // Set up cache for this user (need to instantiate for setupCache)
            self::setupCache(self::$cachedDto);
        }

        return self::$cachedDto;
    }

    /**
     * Set up the user cache with fake PX User data.
     */
    private static function setupCache(PxUserDataWithPermissions $fakeDto): void
    {
        $cacheTime = config('px-user.px_user_cache_time', 120);

        // Cache user attributes that would normally be cached
        $cachedData = [
            'email' => $fakeDto->email,
            'firstname' => $fakeDto->firstname,
            'lastname' => $fakeDto->lastname,
            'preferredUsername' => $fakeDto->preferredUsername,
        ];

        // Cache with the standard key format
        $cacheKey = cache_key('px-user', [
            'class' => config('px-user.user_model'),
            'key' => $fakeDto->id,
        ])->toString();

        Cache::put($cacheKey, $cachedData, now()->addMinutes($cacheTime));

        // Also cache the full user details
        $detailsCacheKey = cache_key('px-user-details', ['id' => $fakeDto->id])->toString();
        Cache::put($detailsCacheKey, $fakeDto->toArray(), now()->addMinutes($cacheTime));
    }

    /**
     * Clear all fake data and mocks.
     */
    public function clear(): void
    {
        Cache::flush();
        Mockery::close();

        app()->bind(PxUser::class, function () {
            return new PxUser;
        });
    }
}
