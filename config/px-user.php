<?php

return [

    /**
     * User model used to Authenticate the user
     */
    'user_model' => '',

    'debug' => env('PX_USER_DEBUG', false),

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
     * Configuration regarding the sanctum integration.
     */
    'sanctum' => [

        /**
         * Enable or disable integration for laravel sanctum.
         * If the integration is enabled you should set
         * config('sanctum.expiration') to 'null'.
         */
        'enabled' => false,

        /**
         * The custom access token model.
         */
        'access_token_model' => \mindtwo\PxUserLaravel\Sanctum\PersonalAccessToken::class,

    ],

    /**
     * Permissions
     *
     * TODO: clean up later
     */
    'admin_emails' => [
    ],

    /**
     * Admin role value
     *
     * TODO: clean up later
     */
    'admin_role_value' => 0,
];
