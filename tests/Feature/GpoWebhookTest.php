<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Testing\TestResponse;
use WeBRTeu\Payments\Enums\PaymentStatus;
use WeBRTeu\Payments\Events\PaymentStatusChanged;
use WeBRTeu\Payments\Models\Payment;

function postGpo(string $body, ?string $header): TestResponse
{
    $server = ['CONTENT_TYPE' => 'application/json'];

    if ($header !== null) {
        $server['HTTP_OPENPAYU_SIGNATURE'] = $header;
    }

    return test()->call('POST', '/webhooks/payments/payu', [], [], [], $server, $body);
}

beforeEach(function (): void {
    Config::set('payu.driver', 'payu-gpo');
    Event::fake([PaymentStatusChanged::class]);
    Payment::query()->create(['gateway' => 'payu', 'order_id' => '123-1', 'amount' => 14170, 'status' => PaymentStatus::Pending]);
});

it('marks COMPLETED as Paid and answers 200 without a body', function (): void {
    $body = fixtureFile('gpo/notification-completed.json');

    postGpo($body, gpoSignatureHeader($body))->assertOk()->assertContent('');

    expect(Payment::query()->sole())->status->toBe(PaymentStatus::Paid)->provider_payment_id->toBe('ORD1');
});

it('keeps Paid when PENDING arrives after COMPLETED', function (): void {
    $completed = fixtureFile('gpo/notification-completed.json');
    $pending = fixtureFile('gpo/notification-pending.json');

    postGpo($completed, gpoSignatureHeader($completed, 'SHA-256'))->assertOk();
    postGpo($pending, gpoSignatureHeader($pending))->assertOk();

    expect(Payment::query()->sole()->status)->toBe(PaymentStatus::Paid);
    Event::assertDispatchedTimes(PaymentStatusChanged::class, 1);
});

it('marks a finalized refund as Refunded', function (): void {
    $completed = fixtureFile('gpo/notification-completed.json');
    $refund = fixtureFile('gpo/notification-refund.json');

    postGpo($completed, gpoSignatureHeader($completed))->assertOk();
    postGpo($refund, gpoSignatureHeader($refund))->assertOk();

    expect(Payment::query()->sole()->status)->toBe(PaymentStatus::Refunded);
});

it('ignores a refund that is not finalized', function (): void {
    $body = (string) json_encode(['orderId' => 'ORD1', 'extOrderId' => '123-1', 'refund' => ['refundId' => 'RF2', 'status' => 'PENDING']]);

    postGpo($body, gpoSignatureHeader($body))->assertOk();

    expect(Payment::query()->sole()->status)->toBe(PaymentStatus::Pending);
});

it('rejects an invalid signature with 400', function (?string $header): void {
    postGpo(fixtureFile('gpo/notification-completed.json'), $header)->assertStatus(400);

    expect(Payment::query()->sole()->status)->toBe(PaymentStatus::Pending);
})->with([null, 'signature=abc;algorithm=MD5']);

it('rejects a signed body that is not JSON', function (): void {
    postGpo('x', gpoSignatureHeader('x'))->assertStatus(400);
});
