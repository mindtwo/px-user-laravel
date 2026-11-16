<?php

namespace mindtwo\PxUserLaravel\Testing;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use mindtwo\PxUserLaravel\Helper\Utils;

/**
 * Builds user payloads in the snake_case shape the PX User API answers with.
 *
 * @internal Use PxUserData::payload() or PxUserDataWithPermissions::payload() instead.
 */
final class PxUserPayload
{
    /**
     * The keys of a `v1/user` or `v1/users/details` user.
     */
    public const USER_KEYS = [
        'id', 'email', 'preferred_username', 'tenant_code', 'domain_code', 'is_enabled', 'is_confirmed',
        'firstname', 'lastname', 'activated_at', 'last_login_at', 'roles', 'products', 'source', 'locale',
    ];

    /**
     * Build a `v1/user-with-permissions` user, with fixed defaults for every key not given.
     *
     * @param  Arrayable<string, mixed>|array<string, mixed>  $attributes  Overrides in snake_case or camelCase
     * @return array<string, mixed>
     */
    public static function make(Arrayable|array $attributes = []): array
    {
        $attributes = self::normalize($attributes);

        $id = (string) ($attributes['id'] ?? Str::uuid());
        $tenantCode = $attributes['tenant_code'] ?? config('px-user.tenant') ?? 'test-tenant';
        $domainCode = $attributes['domain_code'] ?? config('px-user.domain') ?? 'test-domain';
        $products = $attributes['products'] ?? [$attributes['product_code'] ?? Utils::productCode()];
        $roles = $attributes['roles'] ?? [];

        return array_merge([
            'id' => $id,
            'correlated_id' => $id,
            'email' => "{$id}@example.com",
            'preferred_username' => $id,
            'tenant_code' => $tenantCode,
            'domain_code' => $domainCode,
            'is_enabled' => true,
            'is_confirmed' => true,
            'suspended' => false,
            'is_human' => true,
            'firstname' => 'Test',
            'lastname' => 'User',
            'gender' => '',
            'activated_at' => null,
            'last_login_at' => null,
            'last_activity_at' => null,
            'source' => 'test',
            'locale' => config('app.locale'),
            'roles' => $roles,
            'products' => $products,
            'capabilities' => self::capabilities($tenantCode, $domainCode, $products, (array) $roles),
            'products_validity' => null,
        ], Arr::except($attributes, ['product_code']));
    }

    /**
     * Convert overrides to snake_case keys and ISO 8601 dates.
     *
     * @param  Arrayable<string, mixed>|array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public static function normalize(Arrayable|array $attributes): array
    {
        $attributes = $attributes instanceof Arrayable ? $attributes->toArray() : $attributes;
        $normalized = [];

        foreach ($attributes as $key => $value) {
            $normalized[Str::snake((string) $key)] = $value instanceof DateTimeInterface
                ? CarbonImmutable::instance($value)->toIso8601String()
                : $value;
        }

        return $normalized;
    }

    /**
     * Build the capability tree PX sends for the user's tenant, domain and products.
     *
     * @param  array<int|string, mixed>  $products
     * @param  array<string, mixed>  $roles
     * @return array<string, mixed>
     */
    private static function capabilities(string $tenantCode, string $domainCode, array $products, array $roles): array
    {
        $productCodes = array_is_list($products) ? $products : array_keys($products);

        return [
            'tenants' => [
                $tenantCode => [
                    'code' => $tenantCode,
                    'domains' => [
                        $domainCode => [
                            'code' => $domainCode,
                            'products' => collect($productCodes)
                                ->mapWithKeys(fn (string $code) => [$code => ['roles' => $roles[$code] ?? [], 'permissions' => []]])
                                ->all(),
                        ],
                    ],
                ],
            ],
        ];
    }
}
