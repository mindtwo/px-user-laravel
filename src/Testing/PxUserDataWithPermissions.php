<?php

namespace mindtwo\PxUserLaravel\Testing;

use Carbon\CarbonImmutable;
use Faker\Factory;
use mindtwo\PxUserLaravel\DataTransfer\PxUserDataWithPermissions as DataTransferPxUserDataWithPermissions;
use RuntimeException;

class PxUserDataWithPermissions extends DataTransferPxUserDataWithPermissions
{
    /**
     * Create a fake instance for testing purposes.
     *
     * @param  array<string, mixed>  $attributes  Optional attributes to override
     *
     * @throws RuntimeException if not running unit tests
     */
    public static function fake(array $attributes = []): self
    {
        if (! app()->runningUnitTests()) {
            throw new RuntimeException('fake() method can only be called during unit tests.');
        }

        $faker = Factory::create();
        $productCode = $attributes['product_code'] ?? $faker->word();
        $tenantCode = $attributes['tenant_code'] ?? $faker->word();
        $domainCode = $attributes['domain_code'] ?? $faker->word();

        return new self(
            id: $attributes['id'] ?? $faker->uuid(),
            correlatedId: $attributes['correlated_id'] ?? $faker->uuid(),
            email: $attributes['email'] ?? $faker->safeEmail(),
            preferredUsername: $attributes['preferred_username'] ?? $faker->userName(),
            tenantCode: $tenantCode,
            domainCode: $domainCode,
            isEnabled: $attributes['is_enabled'] ?? true,
            isConfirmed: $attributes['is_confirmed'] ?? true,
            suspended: $attributes['suspended'] ?? false,
            isHuman: $attributes['is_human'] ?? true,
            firstname: $attributes['firstname'] ?? $faker->firstName(),
            lastname: $attributes['lastname'] ?? $faker->lastName(),
            gender: $attributes['gender'] ?? $faker->randomElement(['male', 'female', 'diverse']),
            lastLoginAt: $attributes['last_login_at'] ?? CarbonImmutable::now()->subHours($faker->numberBetween(1, 24)),
            lastActivityAt: $attributes['last_activity_at'] ?? CarbonImmutable::now()->subMinutes($faker->numberBetween(1, 60)),
            source: $attributes['source'] ?? 'test',
            locale: $attributes['locale'] ?? $faker->randomElement(['en', 'de', 'fr', 'es']),
            products: $attributes['products'] ?? [$productCode],
            capabilities: $attributes['capabilities'] ?? [
                'tenants' => [
                    $tenantCode => [
                        'domains' => [
                            $domainCode => [
                                'products' => [
                                    $productCode => [
                                        'roles' => ['user'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            roles: $attributes['roles'] ?? null,
            productsValidity: $attributes['products_validity'] ?? null,
        );
    }
}
