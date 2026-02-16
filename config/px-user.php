<?php

return [

    /**
     * User model used to Authenticate the user
     */
    'user_model' => '',

    /**
     * Key from User Model for PX User ID
     *
     * Default: px_user_id
     */
    'px_user_id' => env('PX_USER_ID') ?? 'px_user_id',

    /**
     * The stage the app runs in
     *
     * Default: env('APP_ENV')
     */
    'stage' => env('PX_USER_STAGE', (env('APP_ENV') === 'local' ? 'preprod' : 'prod')),

    /**
     * Api Client config
     */
    'apiClient' => [
        /**
         * Base URL for the PX User API
         */
        'baseUrl' => env('PX_USER_API_URL'),

        /**
         * Request headers
         */
        'headers' => [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ],

        /**
         * Request timeout in seconds
         */
        'timeout' => env('PX_USER_API_TIMEOUT', 30),

        /**
         * Connection timeout in seconds
         */
        'connectTimeout' => env('PX_USER_API_CONNECT_TIMEOUT', 10),

        /**
         * Number of retry attempts for failed requests
         */
        'retries' => env('PX_USER_API_RETRIES', 3),

        /**
         * Retry delay in milliseconds or closure
         * Default: exponential backoff (attempt * 300ms)
         */
        'retryDelay' => null,

        /**
         * Enable debug logging for all requests
         */
        'debug' => env('PX_USER_API_DEBUG', false),

        /**
         * Log level for error logging
         */
        'logLevel' => env('PX_USER_API_LOG_LEVEL', 'error'),

        /**
         * Optional callback to configure the HTTP client after initialization.
         * Receives a PendingRequest instance that can be further customized.
         *
         * Example:
         * 'configure_client' => function (\Illuminate\Http\Client\PendingRequest $client) {
         *     $client->withToken('bearer-token')
         *            ->withBasicAuth('username', 'password')
         *            ->withOptions(['verify' => false]);
         * },
         */
        'configure_client' => null,
    ],

    /**
     * PX User tenant setting
     *
     * Default: env('PX_USER_TENANT')
     */
    'tenant' => env('PX_USER_TENANT'),

    /**
     * PX User domain setting
     *
     * Default: env('PX_USER_DOMAIN')
     */
    'domain' => env('PX_USER_DOMAIN'),

    /**
     * Machine-to-machine credentials used for communication between backend
     * and PX User API
     *
     * Default: env('PX_USER_M2M')
     */
    'm2m_credentials' => env('PX_USER_M2M'),

    /**
     * Cache time for user data retrieved via PX User client in minutes
     *
     * Default: 120 (mins)
     */
    'px_user_cache_time' => 120,

    'px_user_login_url' => env('PX_USER_LOGIN_URL', '/'),

    /**
     * Token storage driver for PX User API tokens
     *
     * Options: 'redis', 'eloquent'
     * Default: 'redis'
     */
    'token_driver' => env('PX_USER_TOKEN_DRIVER', 'redis'),

    'scout' => [
        // Default product context for PX User API when searching for users
        'product_code' => env('PX_USER_SCOUT_PRODUCT_CODE', 'lms'),
    ],

/**
 * Invokeable action class used to retrieve user model.
 * The returned model must implement Authenticatable contract.
 * The action may return false if no model can be found for
 * given user data.
 */
    // 'retrieve_user_action' => \mindtwo\PxUserLaravel\Actions\RetrieveUserOnLoginAction::class,
];
