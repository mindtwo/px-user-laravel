<?php

namespace mindtwo\PxUserLaravel\Http\Client;

use Throwable;

class PxUserAdminClient extends PxUserClient
{
    use M2mSecretHeaderTrait;

    /**
     * Machine-to-machine credentials used for communication between backend
     * and PX User API.
     *
     * @var ?string
     */
    protected $m2mCredentials = null;

    /**
     * Validate the provided token against the PX User API.
     */
    public function validateToken(?string $token): bool
    {
        if (! $token) {
            return false;
        }

        try {
            $response = $this->get("validate-token/$token")->throw();
        } catch (Throwable $e) {
            if (config('px-user.debug')) {
                // Log::info("Failed to login user for url: {$this->getUri()}", [
                //     'message' => $e->getMessage(),
                //     'url' => $this->baseUrl,
                // ]);
            }

            return false;
        }

        return $response->ok();
    }
}
