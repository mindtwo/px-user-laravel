<?php

return [

    /**
     * User model used to Authenticate the user
     */
    'user_model' => '',

    'debug' => env('PX_USER_DEBUG', false),

    'px_user_login_url' => env('PX_USER_LOGIN_URL', '/'),

    /**
     * The stage the app runs in
     *
     * Default: env('APP_ENV')
     */
    'stage' => env('PX_USER_STAGE', (env('APP_ENV') === 'local' ? 'preprod' : 'prod')),

    /**
     * Machine-to-machine credentials used for communication between backend
     * and PX User API
     *
     * Default: env('PX_USER_M2M')
     */
    'm2m_credentials' => env('PX_USER_M2M'),

    /**
     * PX User tenant setting
     *
     * Default: env('PX_USER_TENANT')
     */
    'tenant_code' => env('PX_USER_TENANT'),

    /**
     * PX User domain setting
     *
     * Default: env('PX_USER_DOMAIN')
     */
    'domain_code' => env('PX_USER_DOMAIN'),

    /**
     * Key from User Model for PX User ID
     *
     * Default: px_user_id
     */
    'px_user_id' => env('PX_USER_ID') ?? 'px_user_id',

    /**
     * Cache time for user data retrieved via PX User client in minutes
     *
     * Default: 120 (mins)
     */
    'px_user_cache_time' => 120,

    'configure_px_admin_client' => null,

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
    'retrieve_user_action' => \mindtwo\PxUserLaravel\Actions\RetrieveUserOnLoginAction::class,

    /**
     * The drivers used to authenticate the user.
     */
    'driver' => [

        'default' => 'sanctum',

        /**
         * Configuration regarding the sanctum session driver.
         */
        'sanctum' => [
            /**
             * The custom access token model.
             */
            'access_token_model' => \mindtwo\PxUserLaravel\Driver\Sanctum\Models\PersonalAccessToken::class,

            /**
             * The session driver used to authenticate the user.
             */
            'session_driver' => \mindtwo\PxUserLaravel\Driver\Sanctum\SanctumSessionDriver::class,

            /**
             * Use the ttl given by the PX User API for the access token.
             */
            'use_api_ttl' => env('PX_USER_SANCTUM_USE_API_TTL', false),

            /**
             * The maximum minutes we keep the data in the cache in minutes.
             * Default: 720 (mins)/12 hours
             */
            'max_cache_time' => env('PX_USER_SANCTUM_MAX_CACHE_TIME', 720),
        ],

        'web' => [
            'session_driver' => \mindtwo\PxUserLaravel\Driver\Session\WebSessionDriver::class,
        ],
    ],
];
