<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use WeBRTeu\Payments\Enums\PaymentStatus;
use WeBRTeu\Payments\Models\Payment;
use WeBRTeu\PayU\Facades\PayU;

it('refunds, captures and voids on PayU RO', function (): void {
    Config::set('payu.driver', 'payu-ro');
    Payment::query()->create(['gateway' => 'payu', 'order_id' => '123-1', 'provider_payment_id' => 'PAYU-R1', 'amount' => 14170, 'status' => PaymentStatus::Paid]);
    Http::fake([
        PAYU_RO.'/api/v4/payments/refund' => Http::sequence()->push(fixtureFile('ro/refund.json'))->push(['status' => 'FAILED'])->push([]),
        PAYU_RO.'/api/v4/payments/capture' => Http::response(['status' => 'SUCCESS']),
    ]);

    expect(PayU::refund('PAYU-R1', 5000))->status->toBe(PaymentStatus::Refunded)->providerRefundId->toBe('REF-1')
        ->and(PayU::refund('PAYU-R1', 5000)->status)->toBe(PaymentStatus::Failed)
        ->and(PayU::capture('PAYU-R1', 14170))->status->toBe(PaymentStatus::Paid)->orderId->toBe('123-1')
        ->and(PayU::void('PAYU-R1')->status)->toBe(PaymentStatus::Cancelled);

    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/refund')
        && $request['originalAmount'] === 141.7 && $request['amount'] === 50 && $request['currency'] === 'RON');
});

it('uses the given amount when the payment is unknown on PayU RO', function (): void {
    Config::set('payu.driver', 'payu-ro');
    Http::fake([PAYU_RO.'/*' => Http::response([])]);

    expect(PayU::refund('X', 100))->providerRefundId->toBeNull();
    PayU::void('X');

    Http::assertSent(fn (Request $request): bool => $request['originalAmount'] === 1 && $request['currency'] === 'RON');
});

it('refunds, captures and voids on PayU GPO', function (): void {
    Config::set('payu.driver', 'payu-gpo');
    Http::fake([
        GPO.'/pl/*' => Http::response(fixtureFile('gpo/oauth.json')),
        GPO.'/api/v2_1/orders/ORD1/refunds' => Http::sequence()->push(fixtureFile('gpo/refund.json'))->push(['refund' => ['refundId' => 'RF2', 'status' => 'FINALIZED']])->push([]),
        GPO.'/api/v2_1/orders/ORD1/status' => Http::response(['status' => ['statusCode' => 'SUCCESS']]),
        GPO.'/api/v2_1/orders/ORD1' => Http::response(['status' => ['statusCode' => 'SUCCESS']]),
    ]);

    expect(PayU::refund('ORD1', 5000))->status->toBe(PaymentStatus::Pending)->providerRefundId->toBe('RF1')
        ->and(PayU::refund('ORD1', 5000)->status)->toBe(PaymentStatus::Refunded)
        ->and(PayU::refund('ORD1', 5000)->providerRefundId)->toBeNull()
        ->and(PayU::capture('ORD1', 14170)->status)->toBe(PaymentStatus::Paid)
        ->and(PayU::void('ORD1')->status)->toBe(PaymentStatus::Cancelled);

    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/refunds') && $request['refund']['amount'] === '5000');
    Http::assertSent(fn (Request $request): bool => $request->method() === 'PUT' && $request['orderStatus'] === 'COMPLETED');
    Http::assertSent(fn (Request $request): bool => $request->method() === 'DELETE');
});

it('reads the GPO order status', function (): void {
    Config::set('payu.driver', 'payu-gpo');
    Http::fake([
        GPO.'/pl/*' => Http::response(fixtureFile('gpo/oauth.json')),
        GPO.'/api/v2_1/orders/*' => Http::sequence()->push(fixtureFile('gpo/order-status.json'))->push(['orders' => []]),
    ]);

    expect(PayU::status('ORD1'))->status->toBe(PaymentStatus::Paid)->orderId->toBe('123-1')->amount->toBe(14170)
        ->and(PayU::status('ORD1')->amount)->toBeNull();
});
