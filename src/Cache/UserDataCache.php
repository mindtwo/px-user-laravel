<?php

namespace mindtwo\PxUserLaravel\Cache;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use mindtwo\PxUserLaravel\Facades\PxUserSession;
use mindtwo\PxUserLaravel\Http\Client\PxClient;
use mindtwo\PxUserLaravel\Http\Client\PxUserClient;
use mindtwo\TwoTility\Cache\Data\DataCache;

/**
 * @extends DataCache<Model>
 */
class UserDataCache extends DataCache
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

    protected bool $loadOnlyOnce = true;

    protected function ttl(): int
    {
        return config('px-user.px_user_cache_time') * 60;
    }

    /**
     * Get cache key.
     */
    public function cacheKey(): string
    {
        return cache_key('data_cache', [
            'name' => 'user',
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
        if (! empty($this->initialData)) {
            return $this->initialData;
        }

        if (! $this->checkModel()) {
            return [];
        }

        $client = app()->make(PxClient::class, [
            'tenantCode' => $this->model->tenant_code,
            'domainCode' => $this->model->domain_code,
        ]);

        $accessTokenHelper = PxUserSession::newAccessTokenHelper($this->model);
        if (! $accessTokenHelper->get('access_token')) {
            return [];
        }

        // Check if the user is the same as the authenticated user.
        if (Auth::id() !== $this->model->id) {
            // TODO: user details?
            Log::info('UserdataCache: User is not the same as the authenticated user.', [
                'auth_user' => Auth::id(),
                'model_user' => $this->model->id,
                'request_user' => request()->user()?->id,
                'entrypoint' => request()->path(),
                'called_from' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1]['function'],
            ]);

            return array_fill_keys($this->keys(), null);
        }

        try {
            $userData = $client->get(PxUserClient::USER, [
                'headers' => [
                    'Authorization' => 'Bearer '.$accessTokenHelper->get('access_token'),
                ],
            ])
                ->json('response');
        } catch (\Throwable $th) {
            if (! $th instanceof RequestException || ! in_array($th->response->status(), [401, 403])) {
                throw $th;
            }

            abort($th->response->status());
        }
        $userData = $userData['user'] ?? null;

        if (empty($userData)) {
            return array_fill_keys($this->keys(), null);
        }

        return array_intersect_key($userData, array_flip($this->keys()));
    }

    protected function checkModel(): bool
    {
        if (! $this->model instanceof Authenticatable) {
            return false;
        }

        if (! isset($this->model->{config('px-user.px_user_id')}) || ! $this->model->tenant_code || ! $this->model->domain_code) {
            return false;
        }

        return true;
    }

    protected function canLoad(): bool
    {
        return isset($this->model->{config('px-user.px_user_id')});
    }

    public function keys(): array
    {
        return $this->usedKeys;
    }

    /**
     * Initialize data cache.
     *
     * @return void
     */
    public static function initialize(array $initialData)
    {
        $key = cache_key('data_cache', [
            'name' => 'user',
            'uuid' => $initialData['id'],
        ])->toString();

        Cache::put($key, array_intersect_key($initialData, array_flip([
            'email',
            'firstname',
            'lastname',
            'roles',
            'products',
            'preferred_username',
        ])), config('px-user.px_user_cache_time') * 60);
    }
}
