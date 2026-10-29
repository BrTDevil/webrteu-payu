<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use WeBRTeu\Payments\Data\ItemData;
use WeBRTeu\Payments\Facades\Payments;
use WeBRTeu\Payments\Models\Payment;
use WeBRTeu\PayU\Exceptions\PayUException;
use WeBRTeu\PayU\Gpo\GpoClient;

beforeEach(function (): void {
    Config::set('payu.driver', 'payu-gpo');
});

it('does not follow the 302 and returns the redirectUri', function (): void {
    Http::fake([
        GPO.'/pl/standard/user/oauth/authorize' => Http::response(fixtureFile('gpo/oauth.json')),
        GPO.'/api/v2_1/orders' => Http::response(fixtureFile('gpo/order-created-302.json'), 302, ['Location' => 'https://merch-prod.snd.payu.com/pay/?orderId=ORD1']),
    ]);

    $redirect = Payments::start('payu', paymentRequest(items: [new ItemData('Tricou', 'TR-1', 7085, 2)]));

    expect($redirect->url)->toBe('https://merch-prod.snd.payu.com/pay/?orderId=ORD1')
        ->and(Payment::query()->sole()->provider_payment_id)->toBe('ORD1');

    Http::assertSentCount(2);
    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/api/v2_1/orders')
        && $request->hasHeader('Authorization', 'Bearer gpo-token-1')
        && $request['totalAmount'] === '14170'
        && $request['extOrderId'] === '123-1'
        && $request['merchantPosId'] === '300746'
        && $request['notifyUrl'] === 'https://shop.example/webhooks/payments/payu'
        && $request['products'] === [['name' => 'Tricou', 'unitPrice' => '7085', 'quantity' => '2']]);
});

it('uses a single product line and a custom notify url', function (): void {
    Config::set('payu.notify_url', 'https://hook.example/payu');
    Config::set('payu.sandbox', false);
    Http::fake([
        'https://secure.payu.com/pl/*' => Http::response(fixtureFile('gpo/oauth.json')),
        'https://secure.payu.com/api/v2_1/orders' => Http::response(['redirectUri' => 'https://x'], 201),
    ]);

    expect(Payments::gateway('payu')->createPayment(paymentRequest())->providerPaymentId)->toBeNull();
    Http::assertSent(fn (Request $request): bool => ($request['notifyUrl'] ?? null) === 'https://hook.example/payu'
        && $request['products'] === [['name' => 'Comanda #123', 'unitPrice' => '14170', 'quantity' => '1']]);
});

it('fails without a redirectUri or on API errors', function (): void {
    Http::fake([
        GPO.'/pl/*' => Http::response(fixtureFile('gpo/oauth.json')),
        GPO.'/api/v2_1/orders' => Http::sequence()
            ->push(['status' => ['statusCode' => 'ERROR_VALUE_INVALID', 'statusDesc' => 'Invalid totalAmount']], 302)
            ->push(['status' => ['statusDesc' => 'Bad request']], 400),
    ]);

    expect(fn () => Payments::gateway('payu')->createPayment(paymentRequest()))->toThrow(PayUException::class, 'Invalid totalAmount')
        ->and(fn () => Payments::gateway('payu')->createPayment(paymentRequest()))->toThrow(PayUException::class, 'HTTP 400');
});

it('uses a configured base url', function (): void {
    Config::set('payu.gpo.base_url', 'https://proxy.example/');

    expect(app(GpoClient::class)->baseUrl())->toBe('https://proxy.example');
});
