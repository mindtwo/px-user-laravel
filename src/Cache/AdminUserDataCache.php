<?php

namespace mindtwo\PxUserLaravel\Cache;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\App;
use mindtwo\PxUserLaravel\Http\Client\PxAdminClient;
use mindtwo\TwoTility\Cache\Data\DataCache;

/**
 * @extends DataCache<Model>
 */
class AdminUserDataCache extends DataCache
{
    private array $usedKeys = [
        'email',
        'firstname',
        'lastname',
        'is_enabled',
        'is_confirmed',
        'roles',
        'products',
        'preferred_username',
    ];

    /**
     * Cache driver.
     */
    protected ?string $cacheDriver = 'array';

    /**
     * Get cache key.
     */
    protected function cacheKey(): string
    {
        return cache_key('data_cache', [
            'name' => 'admin_cache:user',
            'uuid' => $this->model->{config('px-user.px_user_id')},
        ])->toString();
    }

    /**
     * Get attribute value from data cache.
     *
     * @return array<string, mixed>
     */
    public function cacheData(): array
    {
        if (! App::runningInConsole() || App::runningUnitTests()) {
            return [];
        }

        if (! isset($this->model->{config('px-user.px_user_id')}) || ! $this->model->tenant_code || ! $this->model->domain_code) {
            return [];
        }

        $pxAdmin = new PxAdminClient(
            tenantCode: $this->model->tenant_code,
            domainCode: $this->model->domain_code,
        );

        $userId = $this->model->{config('px-user.px_user_id')};

        try {
            $userData = $pxAdmin->get("user/$userId")
                ->json('response');
        } catch (\Throwable $th) {
            $userData = [];
        }

        $userData = $userData['user'] ?? null;

        if (empty($userData)) {
            return [];
        }

        return array_intersect_key($userData, array_flip($this->usedKeys));
    }

    protected function canLoad(): bool
    {
        if (! App::runningInConsole()) {
            return false;
        }

        return isset($this->model->{config('px-user.px_user_id')});
    }
}