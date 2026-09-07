<?php

namespace mindtwo\PxUserLaravel\Casts;

use Spatie\LaravelData\Casts\Cast;
use Spatie\LaravelData\Casts\DateTimeInterfaceCast;
use Spatie\LaravelData\Casts\IterableItemCast;
use Spatie\LaravelData\Support\Creation\CreationContext;
use Spatie\LaravelData\Support\DataProperty;

/**
 * Casts a timestamp into a date object, resolving blank values to null.
 *
 * The PX User API sends an empty string rather than null for a timestamp a user
 * has not reached yet - `last_login_at` on an account created through the API,
 * for instance. DateTimeInterfaceCast rejects that with CannotCastDate, so
 * every nullable timestamp on the user DTOs goes through this cast instead.
 *
 * Only use it on nullable properties, since a blank value yields null.
 */
class NullableDateTimeCast implements Cast, IterableItemCast
{
    private readonly DateTimeInterfaceCast $cast;

    public function __construct(
        null|string|array $format = null,
        ?string $type = null,
        ?string $setTimeZone = null,
        ?string $timeZone = null,
    ) {
        $this->cast = new DateTimeInterfaceCast($format, $type, $setTimeZone, $timeZone);
    }

    public function cast(DataProperty $property, mixed $value, array $properties, CreationContext $context): mixed
    {
        if ($this->isBlank($value)) {
            return null;
        }

        return $this->cast->cast($property, $value, $properties, $context);
    }

    public function castIterableItem(DataProperty $property, mixed $value, array $properties, CreationContext $context): mixed
    {
        if ($this->isBlank($value)) {
            return null;
        }

        return $this->cast->castIterableItem($property, $value, $properties, $context);
    }

    private function isBlank(mixed $value): bool
    {
        return is_string($value) && trim($value) === '';
    }
}
