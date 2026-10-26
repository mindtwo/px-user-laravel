<?php

namespace mindtwo\PxUserLaravel\Scout;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\LazyCollection;
use Laravel\Scout\Builder;
use Laravel\Scout\Engines\Engine;
use mindtwo\PxUserLaravel\Http\Client\PxUserClient;

class PxUserEngine extends Engine
{
    /**
     * Update the given model in the index.
     *
     * @param  Collection  $models
     * @return void
     */
    public function update($models)
    {
        // We don't store the models on the database, so we can't update entries inside the index
    }

    /**
     * Remove the given model from the index.
     *
     * @param  Collection  $models
     * @return void
     */
    public function delete($models)
    {
        // We don't store the models on the database, so we can't delete entries from the index
    }

    /**
     * Perform the given search on the engine.
     *
     * @return array{results: Collection<int, Model>, total: int}
     */
    public function search(Builder $builder)
    {
        $models = $this->searchModels($builder);

        return [
            'results' => $builder->limit === null ? $models : $models->take($builder->limit)->values(),
            'total' => $models->count(),
        ];
    }

    /**
     * Perform the given search on the engine and return one page of the local users.
     *
     * @param  int  $perPage
     * @param  int  $page
     * @return array{results: Collection<int, Model>, total: int}
     */
    public function paginate(Builder $builder, $perPage, $page)
    {
        $models = $this->searchModels($builder);

        return [
            'results' => $models->forPage($page, $perPage)->values(),
            'total' => $models->count(),
        ];
    }

    /**
     * Pluck and return the primary keys of the given results.
     *
     * @param  array{results: Collection<int, Model>, total: int}  $results
     */
    public function mapIds($results): SupportCollection
    {
        return collect($results['results']->modelKeys());
    }

    /**
     * Map the given results to instances of the given model.
     *
     * @param  array{results: Collection<int, Model>, total: int}  $results
     * @param  Model  $model
     * @return Collection
     */
    public function map(Builder $builder, $results, $model)
    {
        return $results['results'];
    }

    /**
     * Map the given results to instances of the given model via a lazy collection.
     *
     * @param  array{results: Collection<int, Model>, total: int}  $results
     * @param  Model  $model
     * @return LazyCollection
     */
    public function lazyMap(Builder $builder, $results, $model)
    {
        return new LazyCollection($results['results']->all());
    }

    /**
     * Get the total count from a raw result returned by the engine.
     *
     * @param  array{results: Collection<int, Model>, total: int}  $results
     * @return int
     */
    public function getTotalCount($results)
    {
        return $results['total'];
    }

    /**
     * Flush all of the model's records from the engine.
     *
     * @param  Model  $model
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
     * Get all local users matching the PX search, in the order PX returns them.
     *
     * @return Collection<int, Model>
     */
    protected function searchModels(Builder $builder): Collection
    {
        $pxUserIds = $this->queryPxUserSearch($builder);
        if (empty($pxUserIds)) {
            return $builder->model->newCollection();
        }

        $pxUserIdColumn = config('px-user.px_user_id', 'px_user_id');
        $positions = array_flip($pxUserIds);

        $query = $builder->model->newQuery()->whereIn($pxUserIdColumn, $pxUserIds);
        if ($builder->queryCallback) {
            call_user_func($builder->queryCallback, $query);
        }

        return $query->get()
            ->sortBy(fn (Model $model) => $positions[$model->getAttribute($pxUserIdColumn)])
            ->values();
    }

    /**
     * Search PX users by name and return their PX user ids.
     *
     * @return array<int, string>
     */
    protected function queryPxUserSearch(Builder $builder): array
    {
        $query = $builder->query;
        if (blank($query)) {
            return [];
        }

        // Set the tenant and domain from the builder
        $tenant = $this->getTenantFromBuilder($builder);
        $domain = $this->getDomainFromBuilder($builder);

        /** @var PxUserClient $client */
        $client = resolve(PxUserClient::class)
            ->setDomainCode($domain)
            ->setTenantCode($tenant);

        // PX answers a search without matches with 404
        try {
            $users = $client->getUsers($query);
        } catch (RequestException $e) {
            if ($e->response->status() !== 404) {
                throw $e;
            }

            return [];
        }

        return collect($users)->pluck('id')->toArray();
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
