<?php

namespace mindtwo\PxUserLaravel\Testing;

/**
 * The PX User API calls FakePxUser answers, to fail or assert them in tests.
 */
enum PxUserEndpoint: string
{
    case User = 'v1/user';
    case UserWithPermissions = 'v1/user-with-permissions';
    case UsersDetails = 'v1/users/details';
    case Users = 'v1/users';
    case RefreshTokens = 'v1/refresh-tokens';
    case Logout = 'v1/logout';
    case EipCheck = 'v2/eip/check';
    case AdminUser = 'v1/user/{id}';
    case OidcToken = 'oidc/v1.0/token';
}
