<?php

declare(strict_types=1);

use WeBRTeu\Payments\Data\CustomerData;
use WeBRTeu\Payments\Data\ItemData;
use WeBRTeu\Payments\Data\PaymentRequestData;

const GPO = 'https://secure.snd.payu.com';
const PAYU_RO = 'https://secure.payu.ro';

function fixtureFile(string $name): string
{
    return (string) file_get_contents(__DIR__.'/Fixtures/'.$name);
}

/**
 * @param  list<ItemData>  $items
 */
function paymentRequest(string $orderId = '123-1', int $amount = 14170, array $items = []): PaymentRequestData
{
    return new PaymentRequestData(
        orderId: $orderId,
        amount: $amount,
        description: 'Comanda #123',
        customer: new CustomerData('ion@example.com', 'Ion', 'Popescu', '0721000000', 'Brașov'),
        returnUrl: 'https://shop.example/plata/payu/return?order=123',
        items: $items,
        ip: '1.2.3.4',
    );
}

function gpoSignatureHeader(string $body, string $algorithm = 'MD5'): string
{
    $key = 'b6ca15b0d1020e8094d9b5f8d163db54';
    $signature = $algorithm === 'MD5' ? md5($body.$key) : hash('sha256', $body.$key);

    return "sender=checkout;signature={$signature};algorithm={$algorithm};content=DOCUMENT";
}
