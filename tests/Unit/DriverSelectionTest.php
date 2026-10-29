<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Config;
use WeBRTeu\Payments\Facades\Payments;
use WeBRTeu\PayU\Drivers\GpoDriver;
use WeBRTeu\PayU\Drivers\RoDriver;
use WeBRTeu\PayU\Exceptions\PayUException;
use WeBRTeu\PayU\Facades\PayU;

it('picks the driver from PAYU_DRIVER', function (string $driver, string $class): void {
    Config::set('payu.driver', $driver);

    expect(PayU::driver())->toBeInstanceOf($class)
        ->and(PayU::driver()->name())->toBe('payu')
        ->and(Payments::gateway('payu')->name())->toBe('payu');
})->with([['payu-gpo', GpoDriver::class], ['payu-ro', RoDriver::class]]);

it('rejects an unknown driver', function (): void {
    Config::set('payu.driver', 'payu-xx');

    PayU::driver();
})->throws(PayUException::class, 'payu-xx');
