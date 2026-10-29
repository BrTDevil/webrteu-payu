<?php

declare(strict_types=1);

namespace WeBRTeu\PayU\Ro;

use Illuminate\Support\Facades\Log;
use WeBRTeu\Payments\Enums\PaymentStatus;

/**
 * ⚠️ Valorile exacte se confirmă din docs v4.
 */
final class RoStatusMap
{
    public const MAP = [
        'SUCCESS' => PaymentStatus::Paid,
        'COMPLETE' => PaymentStatus::Paid,
        'PAYMENT_AUTHORIZED' => PaymentStatus::Authorized,
        'REFUND' => PaymentStatus::Refunded,
        'REVERSED' => PaymentStatus::Refunded,
        'FAILED' => PaymentStatus::Failed,
        'DECLINED' => PaymentStatus::Failed,
        'PENDING' => PaymentStatus::Pending,
        'IN_PROGRESS' => PaymentStatus::Pending,
        'CANCELED' => PaymentStatus::Cancelled,
    ];

    public static function resolve(string $status): PaymentStatus
    {
        $key = strtoupper($status);

        if (isset(self::MAP[$key])) {
            return self::MAP[$key];
        }

        Log::warning('payu.unknown_status', ['driver' => 'payu-ro', 'status' => $status]);

        return PaymentStatus::Unknown;
    }
}
