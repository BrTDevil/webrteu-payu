<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use WeBRTeu\Payments\Data\ItemData;
use WeBRTeu\Payments\Enums\PaymentStatus;
use WeBRTeu\Payments\Facades\Payments;
use WeBRTeu\Payments\Models\Payment;
use WeBRTeu\PayU\Drivers\RoDriver;
use WeBRTeu\PayU\Exceptions\PayUException;
use WeBRTeu\PayU\Ro\RequestSigner;

beforeEach(function (): void {
    Config::set('payu.driver', 'payu-ro');
    CarbonImmutable::setTestNow('2026-10-28 12:00:00');
});

it('sends the signature headers and returns the payment page url', function (): void {
    Http::fake([PAYU_RO.'/api/v4/payments/authorize' => Http::response(fixtureFile('ro/authorize-ok.json'))]);

    $redirect = Payments::start('payu', paymentRequest(items: [new ItemData('Tricou', 'TR-1', 7085, 2)]));

    expect($redirect->url)->toBe('https://secure.payu.ro/order/pay?ref=abc')
        ->and(Payment::query()->sole()->provider_payment_id)->toBe('PAYU-R1');

    Http::assertSent(function (Request $request): bool {
        $date = CarbonImmutable::now()->toIso8601String();
        $expected = RequestSigner::sign('MERCH1', $date, 'POST', '/api/v4/payments/authorize', '', $request->body(), 'ro-secret');

        return $request->hasHeader('X-Header-Merchant', 'MERCH1')
            && $request->hasHeader('X-Header-Date', $date)
            && $request->hasHeader('X-Header-Signature', $expected)
            && $request['merchantPaymentReference'] === '123-1'
            && $request['authorization']['paymentMethod'] === 'CCVISAMC'
            && $request['products'][0]['unitPrice'] === 70.85;
    });
});

it('sends a single product line without items', function (): void {
    expect(RoDriver::authorizeBody(paymentRequest())['products'])->toBe([
        ['name' => 'Comanda #123', 'sku' => '123-1', 'unitPrice' => 141.7, 'quantity' => 1, 'vat' => 0],
    ]);
});

it('fails without a payment url and on API errors', function (): void {
    Http::fake([PAYU_RO.'/*' => Http::sequence()->push(['status' => 'FAILED', 'message' => 'Merchant invalid'])->push(['message' => 'Unauthorized'], 401)]);

    expect(fn () => Payments::gateway('payu')->createPayment(paymentRequest()))->toThrow(PayUException::class, 'Merchant invalid')
        ->and(fn () => Payments::gateway('payu')->createPayment(paymentRequest()))->toThrow(PayUException::class, 'Unauthorized');
});

it('reads the status by merchantPaymentReference', function (): void {
    Payment::query()->create(['gateway' => 'payu', 'order_id' => '123-1', 'provider_payment_id' => 'PAYU-R1', 'amount' => 14170, 'status' => PaymentStatus::Pending]);
    Http::fake([PAYU_RO.'/api/v4/payments/status/*' => Http::response(fixtureFile('ro/status.json'))]);

    $status = Payments::gateway('payu')->status('PAYU-R1');

    expect($status)->status->toBe(PaymentStatus::Paid)->orderId->toBe('123-1')->amount->toBe(14170);
    Http::assertSent(fn (Request $request): bool => $request->url() === PAYU_RO.'/api/v4/payments/status/123-1' && $request->method() === 'GET');
});

it('falls back to the given id for an unknown payment', function (): void {
    Http::fake([PAYU_RO.'/api/v4/payments/status/*' => Http::response(['status' => 'PENDING'])]);

    expect(Payments::gateway('payu')->status('X-1'))->orderId->toBe('X-1')->providerPaymentId->toBe('X-1')->amount->toBeNull();
});
