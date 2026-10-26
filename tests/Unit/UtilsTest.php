<?php

use mindtwo\PxUserLaravel\Helper\Utils;

test('reads the product code from config', function () {
    config(['px-user.product_code' => 'crm']);

    expect(Utils::productCode())->toBe('crm');
});

test('prefers a published scout.product_code over the new key', function () {
    config(['px-user.product_code' => 'lms', 'px-user.scout.product_code' => 'legacy']);

    expect(Utils::productCode())->toBe('legacy');
});

test('defaults the product code to lms', function () {
    config(['px-user.product_code' => null, 'px-user.scout' => null]);

    expect(Utils::productCode())->toBe('lms');
});

test('lets product_code win over an unmodified legacy scout block', function () {
    config(['px-user.product_code' => 'crm', 'px-user.scout.product_code' => 'lms']);

    expect(Utils::productCode())->toBe('crm');
});
