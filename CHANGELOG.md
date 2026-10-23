# Changelog

All notable changes to `px-user-laravel` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](http://keepachangelog.com/) principles.

## NEXT - YYYY-MM-DD

### Added
- `extended_products_on_login` config option (`PX_USER_EXTENDED_PRODUCTS_ON_LOGIN`) to fetch the user with extended products on login and token refresh
- `PxUser::refreshSession()` refreshes an expiring PX token behind a per-user lock, without logging the user in, and dispatches `PxUserTokenRefreshEvent`
- `refresh_leeway` config option (`PX_USER_REFRESH_LEEWAY`, seconds before expiry a token is refreshed, default 60)
- `RefreshPxUserSession` middleware that refreshes an expiring PX session on stateful web routes instead of aborting with 401

### Deprecated
- Nothing

### Fixed
- `PxUserTokens` no longer serves a stale token after `save()`/`invalidate()`, an expired token to valid-only reads, or another user's token from the same instance
- `PxUserTokens::refreshTokenValidUntil()` no longer throws once the access token has expired

### Removed
- Nothing

### Security
- Nothing
