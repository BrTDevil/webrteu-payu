<?php

declare(strict_types=1);

namespace WeBRTeu\PayU\Gpo;

use WeBRTeu\Payments\Exceptions\InvalidSignatureException;

/**
 * Header-ul `OpenPayu-Signature: sender=checkout;signature=...;algorithm=MD5;content=DOCUMENT`.
 */
final class NotificationSignature
{
    /**
     * @return array<string, string>
     */
    public static function parse(string $header): array
    {
        $parts = [];

        foreach (explode(';', $header) as $pair) {
            [$key, $value] = array_pad(explode('=', trim($pair), 2), 2, '');

            if ($key !== '') {
                $parts[strtolower($key)] = $value;
            }
        }

        return $parts;
    }

    public static function verify(string $rawBody, string $secondKey, ?string $header): void
    {
        $parts = self::parse((string) $header);
        $signature = $parts['signature'] ?? '';

        $expected = match (strtoupper($parts['algorithm'] ?? '')) {
            'MD5' => md5($rawBody.$secondKey),
            'SHA-256', 'SHA256' => hash('sha256', $rawBody.$secondKey),
            default => throw new InvalidSignatureException('Algoritm OpenPayu-Signature neacceptat.'),
        };

        if ($signature === '' || ! hash_equals($expected, strtolower($signature))) {
            throw new InvalidSignatureException('Semnătura OpenPayu-Signature nu este validă.');
        }
    }
}
