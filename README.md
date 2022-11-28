# Laravel PX-User Package

[![Latest Version on Packagist][ico-version]][link-packagist]
[![Software License][ico-license]](LICENSE.md)
[![Total Downloads][ico-downloads]][link-downloads]

## Installation

You can install the package via composer:

```bash
composer require mindtwo/px-user-laravel
```

## How to use?

### Publish config

To publish the modules config file simply run

```bash
php artisan vendor:publish px-user
```
This publishes the `px-user.php` config file to your projects config folder.

### Configure the package

After that you should add the following keys to your .env-file:

- PX_USER_M2M
- PX_USER_TENANT
- PX_USER_DOMAIN

This keys will auto populate the respective config values.

Inside your configuration you will also find the keys:

`stage` which will use your APP_ENV variable and `px_user_cache_time` which
simply determines for how long the package is allowed to cache the user data in
minutes.

### Prepare the User model

First you will need to add a column `px_user_id` to your users table. This value is
used to retrieve the cached user data.

This is necessary since PX User only allows us to cache the user data and not to store them inside
a database, we rely on caching the data. This is done using Laravel `Cache` facade.
To seemlessly integrate the data for use with your `User` model the package provides
a trait.

```php
use mindtwo\PxUserLaravel\Traits\UseUserDataCache;

class User extends Model
{
    use UseUserDataCache;
}
```

This trait overrides the models `getAttribute($name)` method so you can use `$user->lastname`
even though there is no lastname column inside your users table.

### Login a user

To login a user the package provides an action called `PxUserLoginAction`. Utilize this action
inside a controller to retrieve the user data from PX Users api.

An example for such a controller is given below:

```php
use App\Http\Controllers\Controller;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use mindtwo\PxUserLaravel\Actions\PxUserLoginAction;

class LoginController extends Controller
{
    public function __construct(
        protected PxUserLoginAction $pxUserLoginAction,
    ) {
    }

    /**
     * @param  Request  $request
     * @return \Illuminate\Http\JsonResponse
     *
     * @throws Exception
     */
    public function login(Request $request)
    {
        // received token auth data via PX User widgets
        $tokenData = $request->only([
            'access_token',
            'access_token_lifetime_minutes',
            'access_token_expiration_utc',
            'refresh_token',
            'refresh_token_lifetime_minutes',
            'refresh_token_expiration_utc',
        ]);

        $result = $this->pxUserLoginAction->execute($tokenData);

        return response()->json(['success' => $result]);
    }
}
```

If the value for `$result` is true you can now access authenticated user
via Laravel's `Auth` facade.

### Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

### Security

If you discover any security related issues, please email info@mindtwo.de instead of using the issue tracker.

## Credits

- [mindtwo GmbH][link-author]
- [All Other Contributors][link-contributors]

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

[ico-version]: https://img.shields.io/packagist/v/mindtwo/px-user-laravel.svg?style=flat-square
[ico-license]: https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square
[ico-downloads]: https://img.shields.io/packagist/dt/mindtwo/px-user-laravel.svg?style=flat-square
[link-packagist]: https://packagist.org/packages/mindtwo/px-user-laravel
[link-downloads]: https://packagist.org/packages/mindtwo/px-user-laravel
[link-author]: https://github.com/mindtwo
[link-contributors]: ../../contributors
