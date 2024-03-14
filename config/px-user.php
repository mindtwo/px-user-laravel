<?php

return [

    /**
     * User model used to Authenticate the user
     */
    'user_model' => '',

    'debug' => env('PX_USER_DEBUG', false),

    'base_url' => env('PX_USER_BASE_URL', 'https://user.api.preprod.pl-x.cloud'),

    /**
     * The stage the app runs in
     *
     * Default: env('APP_ENV')
     */
    'stage' => env('APP_ENV') === 'local' ? 'preprod' : env('APP_ENV'),

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
    'tenant' => env('PX_USER_TENANT'),

    /**
     * PX User domain setting
     *
     * Default: env('PX_USER_DOMAIN')
     */
    'domain' => env('PX_USER_DOMAIN'),

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

    /**
     * Invokeable action class used to retrieve user model.
     * The returned model must implement Authenticatable contract.
     * The action may return false if no model can be found for
     * given user data.
     */
    'retrieve_user_action' => \mindtwo\PxUserLaravel\Actions\RetrieveUserOnLoginAction::class,

    /**
     * Amount of retries for http requests to px-user's api.
     */
    'http_request_retries' => 3,

    'http_request_retry_delay' => 300,

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

            'session_driver' => \mindtwo\PxUserLaravel\Driver\Sanctum\SanctumSessionDriver::class,
        ],

        'web' => [
            'session_driver' => \mindtwo\PxUserLaravel\Driver\Session\WebSessionDriver::class,
        ],
    ],
];
