<?php

declare(strict_types=1);

use WeBRTeu\Payments\Exceptions\InvalidSignatureException;
use WeBRTeu\PayU\Gpo\NotificationSignature;

it('parses the OpenPayu-Signature header', function (): void {
    expect(NotificationSignature::parse('sender=checkout;signature=abc;algorithm=MD5;content=DOCUMENT; ;x'))->toBe([
        'sender' => 'checkout',
        'signature' => 'abc',
        'algorithm' => 'MD5',
        'content' => 'DOCUMENT',
        'x' => '',
    ]);
});

it('accepts valid MD5 and SHA-256 signatures', function (string $algorithm): void {
    NotificationSignature::verify('{"a":1}', 'b6ca15b0d1020e8094d9b5f8d163db54', gpoSignatureHeader('{"a":1}', $algorithm));

    expect(true)->toBeTrue();
})->with(['MD5', 'SHA-256']);

it('rejects invalid signatures and unknown algorithms', function (?string $header, string $message): void {
    expect(fn () => NotificationSignature::verify('{"a":1}', 'b6ca15b0d1020e8094d9b5f8d163db54', $header))
        ->toThrow(InvalidSignatureException::class, $message);
})->with([
    [null, 'neacceptat'],
    ['signature=abc;algorithm=SHA-1', 'neacceptat'],
    ['signature=abc;algorithm=MD5', 'nu este validă'],
    ['algorithm=MD5', 'nu este validă'],
]);
