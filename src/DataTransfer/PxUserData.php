<?php

namespace mindtwo\PxUserLaravel\DataTransfer;

use Carbon\CarbonImmutable;
use mindtwo\PxUserLaravel\Casts\NullableDateTimeCast;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;
use Spatie\LaravelData\Optional;

#[MapInputName(SnakeCaseMapper::class)]
class PxUserData extends Data
{
    public function __construct(
        public string $id,
        public string $email,
        public string $preferredUsername,
        public string $tenantCode,
        public string $domainCode,
        public bool|Optional $isEnabled,
        public bool|Optional $isConfirmed,
        public string $firstname,
        public string $lastname,
        #[WithCast(NullableDateTimeCast::class)]
        public ?CarbonImmutable $activatedAt,
        #[WithCast(NullableDateTimeCast::class)]
        public ?CarbonImmutable $lastLoginAt,
        /** @var array<string, array<int, string>> */
        public array $roles,
        /** @var array<int, string> */
        public array $products,
        public string|Optional $source,
        public string $locale,
    ) {}

    public static function fromExtendedData(PxUserDataWithPermissions $data): self
    {
        return new self(
            id: $data->id,
            email: $data->email,
            preferredUsername: $data->preferredUsername,
            tenantCode: $data->tenantCode,
            domainCode: $data->domainCode,
            isEnabled: $data->isEnabled,
            isConfirmed: $data->isConfirmed,
            firstname: $data->firstname,
            lastname: $data->lastname,
            activatedAt: null,
            lastLoginAt: $data->lastLoginAt,
            roles: $data->roles,
            products: $data->products,
            source: $data->source,
            locale: $data->locale,
        );
    }
}
