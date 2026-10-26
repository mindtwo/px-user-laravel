# Changelog

All notable changes to `px-user-laravel` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](http://keepachangelog.com/) principles.

## NEXT - YYYY-MM-DD

### Added
- `extended_products_on_login` config option (`PX_USER_EXTENDED_PRODUCTS_ON_LOGIN`) to fetch the user with extended products on login and token refresh

### Deprecated
- Nothing

### Fixed
- `PxUserTokens::invalidate()` only expires valid tokens, so a login, refresh or logout no longer re-stamps `valid_until` on old rows and keeps them from ever being pruned

### Removed
- Nothing

### Security
- Nothing
