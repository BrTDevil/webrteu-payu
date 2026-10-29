<?php

declare(strict_types=1);

namespace WeBRTeu\PayU\Drivers;

use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Config;
use Symfony\Component\HttpFoundation\Response;
use WeBRTeu\Payments\Data\CustomerData;
use WeBRTeu\Payments\Data\ItemData;
use WeBRTeu\Payments\Data\PaymentRedirectData;
use WeBRTeu\Payments\Data\PaymentRequestData;
use WeBRTeu\Payments\Data\PaymentStatusData;
use WeBRTeu\Payments\Data\RefundData;
use WeBRTeu\Payments\Data\WebhookData;
use WeBRTeu\Payments\Enums\PaymentStatus;
use WeBRTeu\Payments\Enums\RedirectType;
use WeBRTeu\Payments\Exceptions\InvalidSignatureException;
use WeBRTeu\Payments\Models\Payment;
use WeBRTeu\Payments\Support\Money;
use WeBRTeu\PayU\Exceptions\PayUException;
use WeBRTeu\PayU\Ro\RequestSigner;
use WeBRTeu\PayU\Ro\RoClient;
use WeBRTeu\PayU\Ro\RoStatusMap;

/**
 * PayU România, API v4. ⚠️ Câmpurile și răspunsul la notificare se confirmă din secure.payu.ro/docs.
 */
class RoDriver implements Driver
{
    public function __construct(private readonly RoClient $client) {}

    public function name(): string
    {
        return 'payu';
    }

    public function createPayment(PaymentRequestData $request): PaymentRedirectData
    {
        $json = $this->client->send('POST', '/api/v4/payments/authorize', self::authorizeBody($request));
        $url = data_get($json, 'paymentResult.url');

        if (! is_string($url) || $url === '') {
            throw PayUException::fromResponse('/api/v4/payments/authorize', 200, $json);
        }

        $reference = data_get($json, 'payuPaymentReference');

        return new PaymentRedirectData(RedirectType::Redirect, $url, [], is_scalar($reference) ? (string) $reference : null);
    }

    /**
     * Notificarea v4 e semnată cu aceeași schemă HMAC, calculată pe metoda, calea și corpul brut primite.
     */
    public function parseWebhook(Request $request): WebhookData
    {
        $raw = $request->getContent();
        $expected = RequestSigner::sign(
            (string) $request->header('X-Header-Merchant', Config::string('payu.ro.merchant', '')),
            (string) $request->header('X-Header-Date'),
            $request->getMethod(),
            '/'.ltrim($request->path(), '/'),
            (string) $request->getQueryString(),
            $raw,
            Config::string('payu.ro.secret', ''),
        );

        if (! RequestSigner::verify($expected, (string) $request->header('X-Header-Signature'))) {
            throw new InvalidSignatureException('Semnătura notificării PayU v4 nu este validă.');
        }

        $json = json_decode($raw, true);

        if (! is_array($json)) {
            throw new InvalidSignatureException('Notificarea PayU nu este JSON valid.');
        }

        $status = $this->string($json, 'status');
        $reference = $this->string($json, 'payuPaymentReference');

        return new WebhookData(
            eventId: $reference.'-'.$status,
            orderId: $this->string($json, 'merchantPaymentReference'),
            status: RoStatusMap::resolve($status),
            providerPaymentId: $reference !== '' ? $reference : null,
            amount: $this->amount($json['amount'] ?? null),
            raw: $json,
        );
    }

    public function webhookResponse(?WebhookData $webhook, bool $accepted): Response
    {
        // ⚠️ Formatul exact de confirmare cerut de v4 se confirmă din docs.
        return new HttpResponse($accepted ? '' : 'Invalid signature', $accepted ? 200 : 400);
    }

    public function status(string $providerPaymentId): PaymentStatusData
    {
        $orderId = $this->orderId($providerPaymentId);
        $json = $this->client->send('GET', '/api/v4/payments/status/'.rawurlencode($orderId));

        return new PaymentStatusData(
            orderId: $orderId,
            status: RoStatusMap::resolve($this->string($json, 'status')),
            providerPaymentId: $this->string($json, 'payuPaymentReference') !== '' ? $this->string($json, 'payuPaymentReference') : $providerPaymentId,
            amount: $this->amount($json['amount'] ?? null),
            raw: $json,
        );
    }

    public function refund(string $providerPaymentId, int $amount): RefundData
    {
        $json = $this->client->send('POST', '/api/v4/payments/refund', $this->operationBody($providerPaymentId, $amount));

        return new RefundData(
            amount: $amount,
            status: $this->string($json, 'status') === '' || strtoupper($this->string($json, 'status')) === 'SUCCESS' ? PaymentStatus::Refunded : RoStatusMap::resolve($this->string($json, 'status')),
            providerRefundId: $this->string($json, 'payuRefundReference') !== '' ? $this->string($json, 'payuRefundReference') : null,
            raw: $json,
        );
    }

    public function capture(string $providerPaymentId, int $amount): PaymentStatusData
    {
        $json = $this->client->send('POST', '/api/v4/payments/capture', $this->operationBody($providerPaymentId, $amount));

        return new PaymentStatusData($this->orderId($providerPaymentId), PaymentStatus::Paid, $providerPaymentId, $amount, $json);
    }

    /**
     * API v4 nu are o operație separată de anulare a pre-autorizării; se face prin refund pe suma integrală. ⚠️
     */
    public function void(string $providerPaymentId): PaymentStatusData
    {
        $payment = Payment::query()->where('gateway', 'payu')->where('provider_payment_id', $providerPaymentId)->first();
        $json = $this->client->send('POST', '/api/v4/payments/refund', $this->operationBody($providerPaymentId, $payment instanceof Payment ? $payment->amount : 0));

        return new PaymentStatusData($this->orderId($providerPaymentId), PaymentStatus::Cancelled, $providerPaymentId, null, $json);
    }

    /**
     * @return array<string, mixed>
     */
    public static function authorizeBody(PaymentRequestData $request): array
    {
        return [
            'merchantPaymentReference' => $request->orderId,
            'currency' => $request->currency,
            'returnUrl' => $request->returnUrl,
            'authorization' => ['paymentMethod' => Config::string('payu.ro.payment_method', 'CCVISAMC'), 'usePaymentPage' => 'YES'],
            'client' => [
                'billing' => self::billing($request->customer),
                'ip' => $request->ip,
            ],
            'products' => $request->items !== []
                ? array_map(fn (ItemData $item): array => [
                    'name' => $item->name,
                    'sku' => $item->sku,
                    'unitPrice' => Money::toFloat($item->unitPrice),
                    'quantity' => $item->quantity,
                    'vat' => $item->vatRate,
                ], $request->items)
                : [['name' => $request->description, 'sku' => $request->orderId, 'unitPrice' => Money::toFloat($request->amount), 'quantity' => 1, 'vat' => 0]],
        ];
    }

    /**
     * @return array<string, string>
     */
    private static function billing(CustomerData $customer): array
    {
        return [
            'firstName' => $customer->firstName,
            'lastName' => $customer->lastName,
            'email' => $customer->email,
            'phone' => (string) $customer->phone,
            'city' => (string) $customer->city,
            'countryCode' => $customer->countryCode,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function operationBody(string $providerPaymentId, int $amount): array
    {
        $payment = Payment::query()->where('gateway', 'payu')->where('provider_payment_id', $providerPaymentId)->first();

        return [
            'payuPaymentReference' => $providerPaymentId,
            'originalAmount' => Money::toFloat($payment instanceof Payment ? $payment->amount : $amount),
            'amount' => Money::toFloat($amount),
            'currency' => $payment instanceof Payment ? $payment->currency : 'RON',
        ];
    }

    /**
     * Statusul v4 se cere după merchantPaymentReference (order_id), nu după referința PayU.
     */
    private function orderId(string $providerPaymentId): string
    {
        $orderId = Payment::query()->where('gateway', 'payu')->where('provider_payment_id', $providerPaymentId)->value('order_id');

        return is_string($orderId) ? $orderId : $providerPaymentId;
    }

    private function amount(mixed $amount): ?int
    {
        return is_numeric($amount) ? Money::fromDecimal(number_format((float) $amount, 2, '.', '')) : null;
    }

    /**
     * @param  array<array-key, mixed>  $data
     */
    private function string(array $data, string $key): string
    {
        return is_scalar($data[$key] ?? null) ? (string) $data[$key] : '';
    }
}
