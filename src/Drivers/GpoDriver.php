<?php

declare(strict_types=1);

namespace WeBRTeu\PayU\Drivers;

use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;
use WeBRTeu\Payments\Data\ItemData;
use WeBRTeu\Payments\Data\PaymentRedirectData;
use WeBRTeu\Payments\Data\PaymentRequestData;
use WeBRTeu\Payments\Data\PaymentStatusData;
use WeBRTeu\Payments\Data\RefundData;
use WeBRTeu\Payments\Data\WebhookData;
use WeBRTeu\Payments\Enums\PaymentStatus;
use WeBRTeu\Payments\Enums\RedirectType;
use WeBRTeu\Payments\Exceptions\InvalidSignatureException;
use WeBRTeu\PayU\Exceptions\PayUException;
use WeBRTeu\PayU\Gpo\GpoClient;
use WeBRTeu\PayU\Gpo\GpoStatusMap;
use WeBRTeu\PayU\Gpo\NotificationSignature;

/**
 * PayU GPO Europe (OpenPayU v2.1). Sumele sunt deja în subunități (bani), ca string.
 */
class GpoDriver implements Driver
{
    public function __construct(private readonly GpoClient $client) {}

    public function name(): string
    {
        return 'payu';
    }

    public function createPayment(PaymentRequestData $request): PaymentRedirectData
    {
        $json = $this->client->send('POST', '/api/v2_1/orders', $this->orderBody($request));
        $url = $json['redirectUri'] ?? null;

        if (! is_string($url) || $url === '') {
            throw PayUException::fromResponse('/api/v2_1/orders', 200, $json);
        }

        return new PaymentRedirectData(RedirectType::Redirect, $url, [], $this->string($json, 'orderId') !== '' ? $this->string($json, 'orderId') : null);
    }

    /**
     * @return array<string, mixed>
     */
    public function orderBody(PaymentRequestData $request): array
    {
        $configured = Config::get('payu.notify_url');

        return [
            'notifyUrl' => is_string($configured) && $configured !== '' ? $configured : URL::route('payments.webhook', ['gateway' => 'payu']),
            'continueUrl' => $request->returnUrl,
            'customerIp' => $request->ip ?? '127.0.0.1',
            'merchantPosId' => (string) Config::get('payu.gpo.pos_id', ''),
            'description' => $request->description,
            'currencyCode' => $request->currency,
            'totalAmount' => (string) $request->amount,
            'extOrderId' => $request->orderId,
            'buyer' => array_filter([
                'email' => $request->customer->email,
                'phone' => $request->customer->phone,
                'firstName' => $request->customer->firstName,
                'lastName' => $request->customer->lastName,
                'language' => $request->locale,
            ], fn (?string $value): bool => $value !== null && $value !== ''),
            'products' => $request->items !== []
                ? array_map(fn (ItemData $item): array => ['name' => $item->name, 'unitPrice' => (string) $item->unitPrice, 'quantity' => (string) $item->quantity], $request->items)
                : [['name' => $request->description, 'unitPrice' => (string) $request->amount, 'quantity' => '1']],
        ];
    }

    public function parseWebhook(Request $request): WebhookData
    {
        $raw = $request->getContent();

        NotificationSignature::verify($raw, Config::string('payu.gpo.second_key', ''), $request->header('OpenPayu-Signature'));

        $json = json_decode($raw, true);

        if (! is_array($json)) {
            throw new InvalidSignatureException('Notificarea PayU nu este JSON valid.');
        }

        if (is_array($json['refund'] ?? null)) {
            $refund = $json['refund'];

            return new WebhookData(
                eventId: 'refund-'.$this->string($refund, 'refundId').'-'.$this->string($refund, 'status'),
                orderId: $this->string($json, 'extOrderId'),
                status: strtoupper($this->string($refund, 'status')) === 'FINALIZED' ? PaymentStatus::Refunded : PaymentStatus::Unknown,
                providerPaymentId: $this->string($json, 'orderId') !== '' ? $this->string($json, 'orderId') : null,
                raw: $json,
            );
        }

        $order = is_array($json['order'] ?? null) ? $json['order'] : [];
        $status = $this->string($order, 'status');

        return new WebhookData(
            eventId: $this->string($order, 'orderId').'-'.$status,
            orderId: $this->string($order, 'extOrderId'),
            status: GpoStatusMap::resolve($status),
            providerPaymentId: $this->string($order, 'orderId') !== '' ? $this->string($order, 'orderId') : null,
            amount: is_numeric($order['totalAmount'] ?? null) ? (int) $order['totalAmount'] : null,
            raw: $json,
        );
    }

    /**
     * HTTP 200 fără corp; orice alt cod → PayU retrimite.
     */
    public function webhookResponse(?WebhookData $webhook, bool $accepted): Response
    {
        return new HttpResponse('', $accepted ? 200 : 400);
    }

    public function status(string $providerPaymentId): PaymentStatusData
    {
        $json = $this->client->send('GET', '/api/v2_1/orders/'.rawurlencode($providerPaymentId));
        $order = data_get($json, 'orders.0');
        $order = is_array($order) ? $order : [];

        return new PaymentStatusData(
            orderId: $this->string($order, 'extOrderId'),
            status: GpoStatusMap::resolve($this->string($order, 'status')),
            providerPaymentId: $providerPaymentId,
            amount: is_numeric($order['totalAmount'] ?? null) ? (int) $order['totalAmount'] : null,
            raw: $json,
        );
    }

    public function refund(string $providerPaymentId, int $amount): RefundData
    {
        $json = $this->client->send('POST', '/api/v2_1/orders/'.rawurlencode($providerPaymentId).'/refunds', [
            'refund' => ['description' => 'Rambursare', 'amount' => (string) $amount],
        ]);

        $refund = is_array($json['refund'] ?? null) ? $json['refund'] : [];

        return new RefundData(
            amount: $amount,
            status: strtoupper($this->string($refund, 'status')) === 'FINALIZED' ? PaymentStatus::Refunded : PaymentStatus::Pending,
            providerRefundId: $this->string($refund, 'refundId') !== '' ? $this->string($refund, 'refundId') : null,
            raw: $json,
        );
    }

    public function capture(string $providerPaymentId, int $amount): PaymentStatusData
    {
        $json = $this->client->send('PUT', '/api/v2_1/orders/'.rawurlencode($providerPaymentId).'/status', [
            'orderId' => $providerPaymentId,
            'orderStatus' => 'COMPLETED',
        ]);

        return new PaymentStatusData('', PaymentStatus::Paid, $providerPaymentId, $amount, $json);
    }

    public function void(string $providerPaymentId): PaymentStatusData
    {
        $json = $this->client->send('DELETE', '/api/v2_1/orders/'.rawurlencode($providerPaymentId));

        return new PaymentStatusData('', PaymentStatus::Cancelled, $providerPaymentId, null, $json);
    }

    /**
     * @param  array<array-key, mixed>  $data
     */
    private function string(array $data, string $key): string
    {
        return is_scalar($data[$key] ?? null) ? (string) $data[$key] : '';
    }
}
