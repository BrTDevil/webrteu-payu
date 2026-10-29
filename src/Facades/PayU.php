<?php

declare(strict_types=1);

namespace WeBRTeu\PayU\Facades;

use Illuminate\Support\Facades\Facade;
use WeBRTeu\PayU\PayUGateway;

/**
 * @method static \WeBRTeu\PayU\Drivers\Driver driver()
 * @method static \WeBRTeu\Payments\Data\PaymentStatusData status(string $providerPaymentId)
 * @method static \WeBRTeu\Payments\Data\RefundData refund(string $providerPaymentId, int $amount)
 * @method static \WeBRTeu\Payments\Data\PaymentStatusData capture(string $providerPaymentId, int $amount)
 * @method static \WeBRTeu\Payments\Data\PaymentStatusData void(string $providerPaymentId)
 *
 * @see PayUGateway
 */
class PayU extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return PayUGateway::class;
    }
}
