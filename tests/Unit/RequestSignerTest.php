<?php

declare(strict_types=1);

use WeBRTeu\PayU\Ro\RequestSigner;

it('builds the v4 string to sign from known values', function (): void {
    $string = RequestSigner::stringToSign('MERCH1', '2026-10-28T12:00:00+02:00', 'post', '/api/v4/payments/authorize', 'a=1', '{"x":1}');

    expect($string)->toBe('MERCH12026-10-28T12:00:00+02:00POST/api/v4/payments/authorize'.md5('a=1').md5('{"x":1}'));
});

it('signs with HMAC-SHA256 and verifies case-insensitively', function (): void {
    $signature = RequestSigner::sign('MERCH1', '2026-10-28T12:00:00+02:00', 'GET', '/api/v4/payments/status/123-1', '', '', 'ro-secret');

    expect($signature)->toBe(hash_hmac('sha256', 'MERCH12026-10-28T12:00:00+02:00GET/api/v4/payments/status/123-1'.md5('').md5(''), 'ro-secret'))
        ->and(RequestSigner::verify($signature, strtoupper($signature)))->toBeTrue()
        ->and(RequestSigner::verify($signature, ''))->toBeFalse()
        ->and(RequestSigner::verify($signature, 'abc'))->toBeFalse();
});
