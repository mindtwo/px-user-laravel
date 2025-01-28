<?php

namespace mindtwo\PxUserLaravel\Data;

use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;

class PxUserPermissionData
{
    public string $id;

    public string $correlated_id;

    public string $email;

    public string $preferred_username;

    public string $tenant_code;

    public string $domain_code;

    public bool $is_enabled;

    public bool $is_confirmed;

    public bool $suspended;

    public bool $is_human;

    public string $firstname;

    public string $lastname;

    public string $gender;

    public Carbon $last_login_at;

    public Carbon $last_activity_at;

    public string $source;

    public ?string $locale;

    /**
     * The products (codes) the user has access to.
     */
    public array $products;

    /**
     * The capabilities set for the user.
     */
    public ?array $capabilities;

    /**
     * The roles keyed by product code.
     */
    public ?array $roles;

    /**
     * The validity of the products the user has access to keyed by product codes.
     */
    public ?array $products_validity;

    public function __construct(
        string $id,
        string $correlated_id,
        string $email,
        string $preferred_username,
        string $tenant_code,
        string $domain_code,
        bool $is_enabled,
        bool $is_confirmed,
        bool $suspended,
        bool $is_human,
        string $firstname,
        string $lastname,
        string $gender,
        string|Carbon $last_login_at,
        string|Carbon $last_activity_at,
        string $source,
        ?string $locale,
        array $products,
        ?array $capabilities,
        ?array $roles,
        ?array $products_validity = null,
    ) {
        $this->id = $id;
        $this->correlated_id = $correlated_id;
        $this->email = $email;
        $this->preferred_username = $preferred_username;
        $this->tenant_code = $tenant_code;
        $this->domain_code = $domain_code;
        $this->is_enabled = $is_enabled;
        $this->is_confirmed = $is_confirmed;
        $this->suspended = $suspended;
        $this->is_human = $is_human;
        $this->firstname = $firstname;
        $this->lastname = $lastname;
        $this->gender = $gender;

        $this->source = $source;
        $this->locale = $locale;

        // Last login at
        $this->last_login_at = is_string($last_login_at) ? Carbon::parse($last_login_at) : $last_login_at;

        // Last activity at
        $this->last_activity_at = is_string($last_activity_at) ? Carbon::parse($last_activity_at) : $last_activity_at;

        // Roles and capabilities
        $this->setRolesAndCapabilities($roles, $capabilities);

        // Products
        $this->setProducts($products, $products_validity);
    }

    /**
     * Set the products the user has access to.
     */
    public function setProducts(array $products, ?array $products_validity = null): void
    {
        // If products validity is set, we simply set it and the products.
        if ($products_validity) {
            $this->products_validity = $products_validity;
            $this->products = $products;

            return;
        }

        $isExtended = ! is_null(Arr::get($products, '0.code'));

        // If products are not extended, we simply set the products.
        if (! $isExtended) {
            $this->products = $products;
            $this->products_validity = null;

            return;
        }

        $this->products = Arr::pluck($products, 'code');
        $this->products_validity = Arr::mapWithKeys($products, function ($product) {
            return [$product['code'] => [
                'valid_from' => $product['valid_from'] ?? null,
                'valid_to' => $product['valid_to'] ?? null,
            ]];
        });
    }

    /**
     * Set the roles and capabilities for the user.
     */
    public function setRolesAndCapabilities(?array $roles, ?array $capabilities): void
    {
        // Always set capabilities.
        $this->capabilities = $capabilities;

        // If roles are set, we simply return them.
        if (! empty($roles)) {
            $this->roles = $roles;
        }

        // If roles are not set, we try to get them from capabilities.
        $tenantCode = $this->tenant_code;
        $domainCode = $this->domain_code;

        // get product capabilities
        $productCapabilities = Arr::get($this->capabilities ?? [], "tenants.{$tenantCode}.domains.{$domainCode}.products", []);

        // get roles productCapabilities map
        $this->roles = Arr::mapWithKeys($productCapabilities, function ($product, $productCode) {
            return [$productCode => $product['roles']];
        });
    }

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            correlated_id: $data['correlated_id'],
            email: $data['email'],
            preferred_username: $data['preferred_username'],
            tenant_code: $data['tenant_code'],
            domain_code: $data['domain_code'],
            is_enabled: $data['is_enabled'] ?? false,
            is_confirmed: $data['is_confirmed'] ?? false,
            suspended: $data['suspended'],
            is_human: $data['is_human'] ?? true,
            firstname: $data['firstname'],
            lastname: $data['lastname'],
            gender: $data['gender'] ?? '',
            last_login_at: $data['last_login_at'],
            last_activity_at: $data['last_activity_at'],
            source: $data['source'] ?? '',
            locale: $data['locale'] ?? null,
            products: $data['products'] ?? [],
            capabilities: $data['capabilities'] ?? null,
            roles: $data['roles'] ?? null,
        );
    }
}
