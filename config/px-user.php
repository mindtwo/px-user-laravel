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
     * Days to keep expired or revoked tokens before they are pruned
     * by `model:prune`. Tokens without a `valid_until` are never pruned.
     *
     * Default: 30 (days)
     */
    'token_retention_days' => env('PX_USER_TOKEN_RETENTION_DAYS', 30),

    /**
     * The stage the app runs in
     *
     * Default: env('APP_ENV')
     */
    'stage' => env('PX_USER_STAGE', (env('APP_ENV') === 'local' ? 'preprod' : 'prod')),

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
    'px_user_cache_time' => env('PX_USER_CACHE_TIME', 120),

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
    'px_user_cache_time' => env('PX_USER_CACHE_TIME', 120),

    /**
     * Request the user's extended products (e.g. products_validity) when resolving
     * the user on login and token refresh
     *
     * Default: false
     */
    'extended_products_on_login' => env('PX_USER_EXTENDED_PRODUCTS_ON_LOGIN', false),

    /**
     * Seconds before expiry at which an access token is refreshed by
     * PxUser::refreshSession() and the RefreshPxUserSession middleware
     *
     * Default: 60 (seconds)
     */
    'refresh_leeway' => env('PX_USER_REFRESH_LEEWAY', 60),

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
    ],

    /**
     * Product code sent as X-Context-Product-Code on user search and user details
     *
     * Default: 'lms'
     */
    'product_code' => env('PX_USER_PRODUCT_CODE', 'lms'),
];
