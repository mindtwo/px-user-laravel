<?php

namespace mindtwo\PxUserLaravel\Testing;

use Carbon\CarbonImmutable;
use Faker\Factory;
use mindtwo\PxUserLaravel\DataTransfer\PxUserData as DataTransferPxUserData;
use RuntimeException;

class PxUserData extends DataTransferPxUserData
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

        return new self(
            id: $attributes['id'] ?? $faker->uuid(),
            email: $attributes['email'] ?? $faker->safeEmail(),
            preferredUsername: $attributes['preferredUsername'] ?? $faker->userName(),
            tenantCode: $attributes['tenantCode'] ?? $faker->word(),
            domainCode: $attributes['domainCode'] ?? $faker->word(),
            isEnabled: $attributes['isEnabled'] ?? true,
            isConfirmed: $attributes['isConfirmed'] ?? true,
            firstname: $attributes['firstname'] ?? $faker->firstName(),
            lastname: $attributes['lastname'] ?? $faker->lastName(),
            activatedAt: $attributes['activatedAt'] ?? CarbonImmutable::now()->subDays($faker->numberBetween(1, 30)),
            lastLoginAt: $attributes['lastLoginAt'] ?? CarbonImmutable::now()->subHours($faker->numberBetween(1, 24)),
            roles: $attributes['roles'] ?? ['user'],
            products: $attributes['products'] ?? [$faker->word()],
            source: $attributes['source'] ?? 'test',
            locale: $attributes['locale'] ?? $faker->randomElement(['en', 'de', 'fr', 'es']),
        );
    }
}
