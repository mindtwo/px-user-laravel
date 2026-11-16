<?php

namespace mindtwo\PxUserLaravel\Testing;

use Illuminate\Contracts\Support\Arrayable;
use mindtwo\PxUserLaravel\DataTransfer\PxUserDataWithPermissions as DataTransferPxUserDataWithPermissions;
use RuntimeException;

class PxUserDataWithPermissions extends DataTransferPxUserDataWithPermissions
{
    /**
     * Create a fake instance for testing purposes.
     *
     * @param  Arrayable<string, mixed>|array<string, mixed>  $attributes  Overrides in snake_case or camelCase
     *
     * @throws RuntimeException if not running unit tests
     */
    public static function fake(Arrayable|array $attributes = []): self
    {
        if (! app()->runningUnitTests()) {
            throw new RuntimeException('fake() method can only be called during unit tests.');
        }

        return self::from(self::payload($attributes));
    }

    /**
     * Build the snake_case user the PX User API answers `v1/user-with-permissions` with.
     *
     * Every key not given gets a fixed default derived from the id, e.g. for Http::fake().
     *
     * @param  Arrayable<string, mixed>|array<string, mixed>  $attributes  Overrides in snake_case or camelCase
     * @return array<string, mixed>
     */
    public static function payload(Arrayable|array $attributes = []): array
    {
        return PxUserPayload::make($attributes);
    }
}
