# Changelog

All notable changes to `px-user-laravel` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](http://keepachangelog.com/) principles.

## NEXT - YYYY-MM-DD

### Added
- Nothing

### Deprecated
- Nothing

### Fixed
- Nothing

### Removed
- `scout.product_code` config and `PX_USER_SCOUT_PRODUCT_CODE`, deprecated in 4.2.0. Set `PX_USER_PRODUCT_CODE` (config `product_code`) instead and remove a `scout` block from your published config

### Security
- Nothing

## 4.2.0 - 2026-10-26

### Added
- `extended_products_on_login` config option (`PX_USER_EXTENDED_PRODUCTS_ON_LOGIN`) to fetch the user with extended products on login and token refresh
- `PxUser::refreshSession()` refreshes an expiring PX token behind a per-user lock, without logging the user in, and dispatches `PxUserTokenRefreshEvent`
- `refresh_leeway` config option (`PX_USER_REFRESH_LEEWAY`, seconds before expiry a token is refreshed, default 60)
- `RefreshPxUserSession` middleware that refreshes an expiring PX session on stateful web routes instead of aborting with 401
- `PxUserClient::getUsersDetails()` sends `X-Context-Product-Code`, from the new `product_code` config option (`PX_USER_PRODUCT_CODE`, default `lms`) or an optional `$productCode` argument
- `PxUserClient::getUsers()` defaults its product context to `product_code`

### Deprecated
- `scout.product_code` config / `PX_USER_SCOUT_PRODUCT_CODE`, replaced by `product_code` / `PX_USER_PRODUCT_CODE`. Both are still honoured; a `scout.product_code` other than `lms` left in a published config takes precedence, so remove the `scout` block

### Fixed
- `PxUserTokens::invalidate()` only expires valid tokens, so a login, refresh or logout no longer re-stamps `valid_until` on old rows and keeps them from ever being pruned

### Removed
- Nothing

### Security
- Nothing
