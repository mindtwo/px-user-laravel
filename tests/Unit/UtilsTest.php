<?php

use mindtwo\PxUserLaravel\Helper\Utils;

test('reads the product code from config', function () {
    config(['px-user.product_code' => 'crm']);

    expect(Utils::productCode())->toBe('crm');
});

test('defaults the product code to lms', function () {
    config(['px-user.product_code' => null]);

    expect(Utils::productCode())->toBe('lms');
});

test('ignores a legacy scout.product_code left in a published config', function () {
    config(['px-user.product_code' => 'crm', 'px-user.scout.product_code' => 'legacy']);

    expect(Utils::productCode())->toBe('crm');
});

test('no longer reads the legacy PX_USER_SCOUT_PRODUCT_CODE env variable', function () {
    $_SERVER['PX_USER_SCOUT_PRODUCT_CODE'] = $_ENV['PX_USER_SCOUT_PRODUCT_CODE'] = 'legacy';

    try {
        $config = require __DIR__.'/../../config/px-user.php';
    } finally {
        unset($_SERVER['PX_USER_SCOUT_PRODUCT_CODE'], $_ENV['PX_USER_SCOUT_PRODUCT_CODE']);
    }

    expect($config['product_code'])->toBe('lms');
});
