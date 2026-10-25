# Changelog

All notable changes to `px-user-laravel` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](http://keepachangelog.com/) principles.

## NEXT - YYYY-MM-DD

### Added
- `extended_products_on_login` config option (`PX_USER_EXTENDED_PRODUCTS_ON_LOGIN`) to fetch the user with extended products on login and token refresh

### Deprecated
- Nothing

### Fixed
- `PxUserClient::getUsersDetails()` skips and logs records that do not hydrate instead of failing the whole batch, so the other users are still returned and cached

### Removed
- Nothing

### Security
- Nothing
