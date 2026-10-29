<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Log;
use WeBRTeu\Payments\Enums\PaymentStatus;
use WeBRTeu\PayU\Gpo\GpoStatusMap;
use WeBRTeu\PayU\Ro\RoStatusMap;

it('maps PayU RO statuses', function (string $status, PaymentStatus $expected): void {
    expect(RoStatusMap::resolve($status))->toBe($expected);
})->with([['SUCCESS', PaymentStatus::Paid], ['complete', PaymentStatus::Paid], ['PAYMENT_AUTHORIZED', PaymentStatus::Authorized], ['REVERSED', PaymentStatus::Refunded], ['DECLINED', PaymentStatus::Failed]]);

it('maps PayU GPO statuses', function (string $status, PaymentStatus $expected): void {
    expect(GpoStatusMap::resolve($status))->toBe($expected);
})->with([['NEW', PaymentStatus::Pending], ['PENDING', PaymentStatus::Pending], ['WAITING_FOR_CONFIRMATION', PaymentStatus::Authorized], ['COMPLETED', PaymentStatus::Paid], ['CANCELED', PaymentStatus::Cancelled]]);

it('logs unknown statuses on both platforms', function (): void {
    Log::spy();

    expect(RoStatusMap::resolve('X'))->toBe(PaymentStatus::Unknown)
        ->and(GpoStatusMap::resolve('Y'))->toBe(PaymentStatus::Unknown);
    Log::shouldHaveReceived('warning')->twice();
});
