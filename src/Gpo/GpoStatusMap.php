<?php

declare(strict_types=1);

namespace WeBRTeu\PayU\Gpo;

use Illuminate\Support\Facades\Log;
use WeBRTeu\Payments\Enums\PaymentStatus;

final class GpoStatusMap
{
    public const MAP = [
        'NEW' => PaymentStatus::Pending,
        'PENDING' => PaymentStatus::Pending,
        'WAITING_FOR_CONFIRMATION' => PaymentStatus::Authorized,
        'COMPLETED' => PaymentStatus::Paid,
        'CANCELED' => PaymentStatus::Cancelled,
    ];

    public static function resolve(string $status): PaymentStatus
    {
        $key = strtoupper($status);

        if (isset(self::MAP[$key])) {
            return self::MAP[$key];
        }

        Log::warning('payu.unknown_status', ['driver' => 'payu-gpo', 'status' => $status]);

        return PaymentStatus::Unknown;
    }
}
