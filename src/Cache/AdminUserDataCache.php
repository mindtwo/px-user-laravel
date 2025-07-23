<?php

namespace mindtwo\PxUserLaravel\Cache;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;
use mindtwo\PxUserLaravel\Http\Client\PxUserAdminClient;
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
        // 'is_enabled',
        // 'is_confirmed',
        'roles',
        'products',
        'preferred_username',
    ];

    protected bool $loadOnAccess = true;

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

        $pxAdmin = $this->getPxAdminClient(new PxUserAdminClient);

        $userId = $this->model->{config('px-user.px_user_id')};

        try {
            $userData = $pxAdmin->get("user/$userId")
                ->json('response');
        } catch (\Throwable $th) {
            Log::error("[PX-User (admin)]: Failed to load data for user $userId.", [
                'message' => $th->getMessage(),
                'code' => $th->getCode(),
                'response' => $th instanceof RequestException ? $th->response->json() : null,
            ]);

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

    protected function getPxAdminClient(PxUserAdminClient $pxAdminClient): PxUserAdminClient
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
