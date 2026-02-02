<?php

namespace mindtwo\PxUserLaravel\Traits;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use mindtwo\PxUserLaravel\Services\PxUserCachedApiService;
use mindtwo\TwoTility\Cache\Models\HasCachedAttributes;
use mindtwo\TwoTility\ExternalApiTokens\ExternalApiTokens;

trait HasPxUser
{
    use HasCachedAttributes {
        HasCachedAttributes::cachedAttributeKey as parentCachedAttributes;
    }

    /**
     * List of cachable attributes.
     */
    protected array $cachableAttributes = [
        'email',
        'firstname',
        'lastname',
        'preferredUsername',
    ];

    /**
     * Get the cache key for data.
     */
    public function cachedAttributeKey(): string
    {
        return cache_key('px-user', [
            'class' => config('px-user.user_model'),
            'key' => $this->getPxUserId(),
        ])->toString();
    }

    /**
     * Get the user's PX User ID.
     */
    public function getPxUserId(): string
    {
        $pxUserIdKey = config('px-user.px_user_id', 'px_user_id');

        return $this->{$pxUserIdKey};
    }

    /**
     * Get the user's PX User domain code.
     */
    public function getPxUserDomainCode(): string
    {
        return $this->px_user_domain_code ?? config('px-user.domain');
    }

    /**
     * Get the user's PX User tenant code.
     */
    public function getPxUserTenantCode(): string
    {
        return $this->px_user_tenant_code ?? config('px-user.tenant');
    }

    /**
     * Get the user's PX User access token.
     */
    public function getPxUserAccessToken(): string
    {
        $repo = resolve(ExternalApiTokens::class)->repository('px-user');

        return $repo->accessToken($this);
    }

    /**
     * Get the user's PX User access token.
     */
    public function hasValidPxUserToken(): bool
    {
        $repo = resolve(ExternalApiTokens::class)->repository('px-user');

        return $repo->isCurrentTokenValid($this);
    }

    /**
     * Hook called before we load the cached attributes.
     */
    protected function beforeCachedAttributeLoad(): void
    {
        $cacheKey = $this->cachedAttributeKey();

        // Check if cache already exists
        if (Cache::store()->has($cacheKey)) {
            return;
        }

        // Fetch user details from API
        try {
            $service = resolve(PxUserCachedApiService::class);
            $service->getUser();
        } catch (\Throwable $e) {
            Log::debug('PX-User: '.$e->getMessage());
            abort(401, 'Unauthenticated');
        }
    }
}
