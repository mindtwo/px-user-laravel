# Changelog

All notable changes to `px-user-laravel` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](http://keepachangelog.com/) principles.

## NEXT - YYYY-MM-DD

### Added
- `RefreshPxUserSession::failedRefresh()`, a no-op hook that runs after a failed refresh has ended the session; override it to add handling

### Deprecated
- Nothing

### Fixed
- `RefreshPxUserSession` logs the user out and ends the session when the PX session cannot be refreshed, and passes the request on to the authentication middleware as a guest. Its 401 kept the user logged in, so guest-only login routes redirected straight back into it. It takes the guard as an optional parameter
- `RefreshPxUserSession` is added to the middleware priority before `AuthenticatesRequests`, so it always runs before the authentication middleware
- `PxUserEngine` implements `paginate()`, so `search()->paginate()` and `search()->simplePaginate()` return the requested page instead of failing
- `PxUserEngine` returns users in the order PX returns them, honours `take()` and applies `query()` callbacks to the local users
- `PxUserEngine` counts only the users stored locally in its total, not every user PX returns
- `PxUserData::$locale` is nullable, like `PxUserDataWithPermissions::$locale`. A user without a locale in PX failed to hydrate, so `PxUserCachedApiService::getUser()` threw and the user's cached attributes could not be loaded, and `PxUserData::fromExtendedData()` threw for the same users

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
