<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use WeBRTeu\PayU\Exceptions\PayUException;
use WeBRTeu\PayU\Facades\PayU;
use WeBRTeu\PayU\Gpo\GpoClient;

beforeEach(function (): void {
    Config::set('payu.driver', 'payu-gpo');
});

it('asks for the OAuth token once', function (): void {
    Http::fake([
        GPO.'/pl/*' => Http::response(fixtureFile('gpo/oauth.json')),
        GPO.'/api/v2_1/orders/ORD1' => Http::response(fixtureFile('gpo/order-status.json')),
    ]);

    PayU::status('ORD1');
    PayU::status('ORD1');

    expect(collect(Http::recorded())->filter(fn (array $pair): bool => str_contains($pair[0]->url(), 'oauth'))->count())->toBe(1)
        ->and(Cache::get(app(GpoClient::class)->tokenKey()))->toBe('gpo-token-1');
    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'oauth')
        && $request['grant_type'] === 'client_credentials'
        && $request['client_secret'] === 'gpo-client-secret');
});

it('forgets the token on 401', function (): void {
    Http::fake([
        GPO.'/pl/*' => Http::response(['access_token' => 'x']),
        GPO.'/api/*' => Http::response([], 401),
    ]);

    expect(fn () => PayU::status('ORD1'))->toThrow(PayUException::class, 'HTTP 401');
    expect(Cache::has(app(GpoClient::class)->tokenKey()))->toBeFalse();
});

it('fails clearly without an access token', function (): void {
    Http::fake([GPO.'/pl/*' => Http::response(['error' => 'invalid_client'])]);

    PayU::status('ORD1');
})->throws(PayUException::class, 'PAYU_GPO_CLIENT_ID');
