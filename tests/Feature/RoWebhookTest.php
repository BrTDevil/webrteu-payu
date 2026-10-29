<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Config;
use Illuminate\Testing\TestResponse;
use WeBRTeu\Payments\Enums\PaymentStatus;
use WeBRTeu\Payments\Models\Payment;
use WeBRTeu\PayU\Ro\RequestSigner;

function postRo(string $body, ?string $signature = null): TestResponse
{
    $date = '2026-10-28T12:00:00+02:00';
    $signature ??= RequestSigner::sign('MERCH1', $date, 'POST', '/webhooks/payments/payu', '', $body, 'ro-secret');

    return test()->call('POST', '/webhooks/payments/payu', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_HEADER_MERCHANT' => 'MERCH1',
        'HTTP_X_HEADER_DATE' => $date,
        'HTTP_X_HEADER_SIGNATURE' => $signature,
    ], $body);
}

beforeEach(function (): void {
    Config::set('payu.driver', 'payu-ro');
    Payment::query()->create(['gateway' => 'payu', 'order_id' => '123-1', 'amount' => 14170, 'status' => PaymentStatus::Pending]);
});

it('accepts a signed notification and marks the payment as paid', function (): void {
    postRo(fixtureFile('ro/notification-success.json'))->assertOk();

    expect(Payment::query()->sole())->status->toBe(PaymentStatus::Paid)->provider_payment_id->toBe('PAYU-R1');
});

it('rejects a wrong signature and a body that is not JSON', function (): void {
    postRo(fixtureFile('ro/notification-success.json'), 'deadbeef')->assertStatus(400)->assertContent('Invalid signature');
    postRo('x')->assertStatus(400);

    expect(Payment::query()->sole()->status)->toBe(PaymentStatus::Pending);
});
