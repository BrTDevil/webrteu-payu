<?php

declare(strict_types=1);

namespace WeBRTeu\PayU\Exceptions;

use WeBRTeu\Payments\Exceptions\PaymentException;

class PayUException extends PaymentException
{
    /**
     * @param  array<array-key, mixed>  $json
     */
    public static function fromResponse(string $operation, int $status, array $json): self
    {
        $message = data_get($json, 'status.statusDesc') ?? data_get($json, 'message') ?? data_get($json, 'meta.message') ?? data_get($json, 'status.statusCode');

        return new self(sprintf('PayU %s a eșuat (HTTP %d): %s', $operation, $status, is_scalar($message) ? (string) $message : 'eroare necunoscută'));
    }
}
