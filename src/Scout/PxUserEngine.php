<?php

namespace mindtwo\PxUserLaravel\Scout;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\LazyCollection;
use Laravel\Scout\Builder;
use Laravel\Scout\Engines\Engine;
use mindtwo\PxUserLaravel\Facades\PxUserSession;
use mindtwo\PxUserLaravel\Http\Client\PxUserClient;

class PxUserEngine extends Engine
{
    /**
     * Update the given model in the index.
     *
     * @param  \Illuminate\Database\Eloquent\Collection  $models
     * @return void
     */
    public function update($models)
    {
        // We don't store the models on the database, so we can't update entries inside the index
    }

    /**
     * Remove the given model from the index.
     *
     * @param  \Illuminate\Database\Eloquent\Collection  $models
     * @return void
     */
    public function delete($models)
    {
        // We don't store the models on the database, so we can't delete entries from the index
    }

    /**
     * Perform the given search on the engine.
     *
     * @return mixed
     */
    public function search(Builder $builder)
    {
        $query = $builder->query;

        if (blank($query)) {
            $query = $builder->model->newQuery();
        }

        $pxUserIds = $this->queryPxUserSearch($builder);
        if (empty($pxUserIds)) {
            return [
                'results' => collect(),
                'total' => 0,
            ];
        }

        return [
            'results' => $builder->model->whereIn(
                config('px-user.px_user_id', 'px_user_id'), $pxUserIds
            )
                ->get(),
            'total' => count($pxUserIds),
        ];
    }

    //     /**
    //      * Paginate the given search on the engine.
    //      *
    //      * @param  \Laravel\Scout\Builder  $builder
    //      * @param  int  $perPage
    //      * @param  int  $page
    //      * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
    //      */
    //     public function paginate(Builder $builder, $perPage, $page)
    //     {
    //         return $this->paginateUsingDatabase($builder, $perPage, 'page', $page);
    //     }
    /**
     * Perform the given search on the engine.
     *
     * @param  int  $perPage
     * @param  int  $page
     * @return mixed
     */
    public function paginate(Builder $builder, $perPage, $page)
    {
        // TODO dump('paginate', $page);
    }

    /**
     * Pluck and return the primary keys of the given results.
     *
     * @param  mixed  $results
     * @return \Illuminate\Support\Collection
     */
    public function mapIds($results)
    {
        $results = $results['results'];

        return count($results) > 0
                    ? collect($results->modelKeys())
                    : collect();
    }

    /**
     * Map the given results to instances of the given model.
     *
     * @param  mixed  $results
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function map(Builder $builder, $results, $model)
    {
        return $results['results'];
    }

    /**
     * Map the given results to instances of the given model via a lazy collection.
     *
     * @param  mixed  $results
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @return \Illuminate\Support\LazyCollection
     */
    public function lazyMap(Builder $builder, $results, $model)
    {
        return new LazyCollection($results['results']->all());
    }

    /**
     * Get the total count from a raw result returned by the engine.
     *
     * @param  mixed  $results
     * @return int
     */
    public function getTotalCount($results)
    {
        return $results['total'];
    }

    /**
     * Flush all of the model's records from the engine.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @return void
     */
    public function flush($model)
    {
        // We don't store the models on the database, so we can't flush the index
    }

    /**
     * Create a search index.
     *
     * @param  string  $name
     * @return mixed
     */
    public function createIndex($name, array $options = [])
    {
        // We don't store the models on the database, so we don't need to create the index
    }

    /**
     * Delete a search index.
     *
     * @param  string  $name
     * @return mixed
     */
    public function deleteIndex($name)
    {
        // We don't store the models on the database, so we don't need to delete the index
    }

    /**
     * Perform the given search on the engine.
     */
    protected function queryPxUserSearch(Builder $builder): array
    {
        $query = $builder->query;
        if (blank($query)) {
            return [];
        }

        $accessTokenHelper = PxUserSession::newAccessTokenHelper(auth()->user());
        if (! $accessTokenHelper->get('access_token')) {
            return [];
        }

        $tenant = $this->getTenantFromBuilder($builder);
        $domain = $this->getDomainFromBuilder($builder);

        /** @var PxUserClient */
        $client = app()->make(PxUserClient::class, [
            'tenantCode' => $tenant,
            'domainCode' => $domain,
        ]);

        // request users
        try {

            $response = $client->client([
                'X-Context-Product-Code' => config('px-user.scout.product_code', 'lms'),
            ])
                ->withToken($accessTokenHelper->get('access_token'))
                ->acceptJson()
                ->asJson()
                ->get('users', [
                    'names' => $query,
                ]);
        } catch (\Throwable $th) {
            if (! $th instanceof RequestException || $th->response->status() !== 404) {
                throw $th;
            }

            $response = $th->response;
        }

        if (! $response->successful()) {
            return [];
        }

        $data = $response->json('response', [])['data'] ?? [];

        // @phpstan-ignore-next-line
        return collect($data)->pluck('id')->toArray();
    }

    /**
     * Get the domain from the builder.
     */
    protected function getDomainFromBuilder(Builder $builder): ?string
    {
        $wheres = $builder->wheres;
        if (empty($wheres)) {
            return null;
        }

        $possibleKeys = ['domain', 'domain_code', 'domain_id', 'domainId', 'domainCode', 'X-Context-Domain-Code'];

        // Get the tenant from the builder
        foreach ($wheres as $key => $value) {
            if (in_array($key, $possibleKeys)) {
                return $value;
            }
        }

        return null;
    }

    /**
     * Get the tenant from the builder.
     */
    protected function getTenantFromBuilder(Builder $builder): ?string
    {
        $wheres = $builder->wheres;
        if (empty($wheres)) {
            return null;
        }

        $possibleKeys = ['tenant', 'tenant_code', 'tenant_id', 'tenantId', 'tenantCode', 'X-Context-Tenant-Code'];

        // Get the tenant from the builder
        foreach ($wheres as $key => $value) {
            if (in_array($key, $possibleKeys)) {
                return $value;
            }
        }

        return null;
    }
}
