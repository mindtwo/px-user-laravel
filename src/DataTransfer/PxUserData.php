<?php

namespace mindtwo\PxUserLaravel\DataTransfer;

use Carbon\CarbonImmutable;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

#[MapInputName(SnakeCaseMapper::class)]
class PxUserData extends Data
{
    public function __construct(
        public string $id,
        public string $email,
        public string $preferredUsername,
        public string $tenantCode,
        public string $domainCode,
        public bool $isEnabled,
        public bool $isConfirmed,
        public string $firstname,
        public string $lastname,
        public ?CarbonImmutable $activatedAt,
        public ?CarbonImmutable $lastLoginAt,
        /** @var array<string, array<int, string>> */
        public array $roles,
        /** @var array<int, string> */
        public array $products,
        public string $source,
        public string $locale,
    ) {}
}
