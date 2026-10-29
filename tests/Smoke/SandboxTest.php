<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Config;
use WeBRTeu\Payments\Facades\Payments;

it('creates a real order on the GPO sandbox', function (): void {
    Config::set('payu.driver', 'payu-gpo');
    Config::set('payu.gpo.pos_id', env('PAYU_GPO_SANDBOX_POS_ID'));
    Config::set('payu.gpo.client_id', env('PAYU_GPO_SANDBOX_CLIENT_ID'));
    Config::set('payu.gpo.client_secret', env('PAYU_GPO_SANDBOX_CLIENT_SECRET'));

    expect(Payments::gateway('payu')->createPayment(paymentRequest('SMOKE-'.time()))->url)->toStartWith('https://');
})->group('sandbox')->skip(! env('PAYU_GPO_SANDBOX_CLIENT_ID'), 'Setează PAYU_GPO_SANDBOX_POS_ID / _CLIENT_ID / _CLIENT_SECRET.');
