<?php

namespace mindtwo\PxUserLaravel\Driver\Contracts;

interface AccessTokenHelper
{
    /**
     * Save token data.
     */
    public function saveTokenData(array $data): void;

    /**
     * Get token data with key and values
     */
    public function values(): array;

    /**
     * Delete token data.
     */
    public function flush(): void;

    /**
     * Put value for passed key
     */
    public function put(string $key, mixed $value): void;

    /**
     * Get value for passed key
     */
    public function get(string $key): mixed;

    /**
     * Check if key is allowed
     */
    public function allowed(string $key): bool;
}
