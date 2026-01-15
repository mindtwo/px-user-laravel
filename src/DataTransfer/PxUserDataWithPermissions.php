<?php

namespace mindtwo\PxUserLaravel\DataTransfer;

use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Casts\DateTimeInterfaceCast;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

#[MapInputName(SnakeCaseMapper::class)]
class PxUserDataWithPermissions extends Data
{
    /**
     * @param  array<int, string>  $products  The products (codes) the user has access to.
     * @param  array<string, mixed>|null  $capabilities  The capabilities set for the user.
     * @param  array<string, array<int, string>>|null  $roles  The roles keyed by product code.
     * @param  array<string, array<string, string|null>>|null  $productsValidity  The validity of the products the user has access to keyed by product codes.
     */
    public function __construct(
        public string $id,
        public string $correlatedId,
        public string $email,
        public string $preferredUsername,
        public string $tenantCode,
        public string $domainCode,
        public bool $isEnabled,
        public bool $isConfirmed,
        public bool $suspended,
        public bool $isHuman,
        public string $firstname,
        public string $lastname,
        public string $gender,
        #[WithCast(DateTimeInterfaceCast::class)]
        public ?CarbonImmutable $lastLoginAt,
        #[WithCast(DateTimeInterfaceCast::class)]
        public ?CarbonImmutable $lastActivityAt,
        public string $source,
        public ?string $locale,
        public array $products,
        public ?array $capabilities,
        public ?array $roles,
        public ?array $productsValidity = null,
    ) {
        // Process products and validity
        $this->processProducts();

        // Process roles and capabilities
        $this->processRolesAndCapabilities();
    }

    /**
     * Process the products the user has access to.
     */
    protected function processProducts(): void
    {
        // If products validity is already set, we're done
        if ($this->productsValidity !== null) {
            return;
        }

        // if the first element is not indexed by 0, we assume the products are extended.
        // We got a map of product code to validity.
        $isExtended = is_null(Arr::get($this->products, '0'));

        // If products are not extended, we're done
        if (! $isExtended) {
            return;
        }

        /** @var array<string, array<string, string>> $products */
        $products = $this->products;

        // Extract product codes and validity information
        $productCodes = Arr::pluck($products, 'code');
        $productsValidity = Arr::mapWithKeys($products, function ($product) {
            return [$product['code'] => [
                'valid_from' => $product['valid_from'] ?? null,
                'valid_to' => $product['valid_to'] ?? null,
            ]];
        });

        $this->products = $productCodes;
        $this->productsValidity = $productsValidity;
    }

    /**
     * Process the roles and capabilities for the user.
     */
    protected function processRolesAndCapabilities(): void
    {
        // If roles are already set, we're done
        if (! empty($this->roles)) {
            return;
        }

        // If no capabilities, set empty roles
        if (empty($this->capabilities)) {
            $this->roles = [];

            return;
        }

        // Extract roles from capabilities
        $productCapabilities = Arr::get(
            $this->capabilities,
            "tenants.{$this->tenantCode}.domains.{$this->domainCode}.products",
            []
        );

        // Map product capabilities to roles
        $this->roles = Arr::mapWithKeys($productCapabilities, function ($product, $productCode) {
            return [$productCode => $product['roles'] ?? []];
        });
    }
}
