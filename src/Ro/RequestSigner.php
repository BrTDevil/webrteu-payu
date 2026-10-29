<?php

declare(strict_types=1);

namespace WeBRTeu\PayU\Ro;

/**
 * Semnătura API v4 PayU România:
 * HMAC-SHA256(merchant + date + method + path + md5(query) + md5(body), secret).
 * ⚠️ Schema se confirmă din secure.payu.ro/docs (API V4).
 */
final class RequestSigner
{
    public static function stringToSign(string $merchant, string $date, string $method, string $path, string $query, string $body): string
    {
        return $merchant.$date.strtoupper($method).$path.md5($query).md5($body);
    }

    public static function sign(string $merchant, string $date, string $method, string $path, string $query, string $body, string $secret): string
    {
        return hash_hmac('sha256', self::stringToSign($merchant, $date, $method, $path, $query, $body), $secret);
    }

    public static function verify(string $expected, string $received): bool
    {
        return $received !== '' && hash_equals(strtolower($expected), strtolower($received));
    }
}
