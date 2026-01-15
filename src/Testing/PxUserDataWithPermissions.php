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
        $productCode = $attributes['productCode'] ?? $faker->word();
        $tenantCode = $attributes['tenantCode'] ?? $faker->word();
        $domainCode = $attributes['domainCode'] ?? $faker->word();

        return new self(
            id: $attributes['id'] ?? $faker->uuid(),
            correlatedId: $attributes['correlatedId'] ?? $faker->uuid(),
            email: $attributes['email'] ?? $faker->safeEmail(),
            preferredUsername: $attributes['preferredUsername'] ?? $faker->userName(),
            tenantCode: $tenantCode,
            domainCode: $domainCode,
            isEnabled: $attributes['isEnabled'] ?? true,
            isConfirmed: $attributes['isConfirmed'] ?? true,
            suspended: $attributes['suspended'] ?? false,
            isHuman: $attributes['isHuman'] ?? true,
            firstname: $attributes['firstname'] ?? $faker->firstName(),
            lastname: $attributes['lastname'] ?? $faker->lastName(),
            gender: $attributes['gender'] ?? $faker->randomElement(['male', 'female', 'diverse']),
            lastLoginAt: $attributes['lastLoginAt'] ?? CarbonImmutable::now()->subHours($faker->numberBetween(1, 24)),
            lastActivityAt: $attributes['lastActivityAt'] ?? CarbonImmutable::now()->subMinutes($faker->numberBetween(1, 60)),
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
            productsValidity: $attributes['productsValidity'] ?? null,
        );
    }
}
