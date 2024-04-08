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

    protected bool $loadOnAccess = true;

    protected bool $loadOnlyOnce = true;

    /**
     * Cache driver.
     */
    protected ?string $cacheDriver = 'array';

    /**
     * Get cache key.
     */
    public function cacheKey(): string
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

        if (! isset($this->model->{config('px-user.px_user_id')})) {
            return [];
        }

        $pxAdmin = $this->getPxAdminClient(new PxAdminClient());

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

    public function keys(): array
    {
        return $this->usedKeys;
    }

    protected function getPxAdminClient(PxAdminClient $pxAdminClient): PxAdminClient
    {
        if (! config('px-user.configure_px_admin_client')) {
            return $pxAdminClient;
        }

        $action = app()->make(config('px-user.configure_px_admin_client'));

        if (! is_callable($action)) {
            return $pxAdminClient;
        }

        return $action($pxAdminClient, $this->model);
    }

    protected function canLoad(): bool
    {
        if (! App::runningInConsole()) {
            return false;
        }

        return isset($this->model->{config('px-user.px_user_id')});
    }
}
