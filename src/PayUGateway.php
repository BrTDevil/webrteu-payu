<?php

declare(strict_types=1);

namespace WeBRTeu\PayU;

use Illuminate\Contracts\Container\Container;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Symfony\Component\HttpFoundation\Response;
use WeBRTeu\Payments\Data\PaymentRedirectData;
use WeBRTeu\Payments\Data\PaymentRequestData;
use WeBRTeu\Payments\Data\PaymentStatusData;
use WeBRTeu\Payments\Data\RefundData;
use WeBRTeu\Payments\Data\WebhookData;
use WeBRTeu\PayU\Drivers\Driver;
use WeBRTeu\PayU\Drivers\GpoDriver;
use WeBRTeu\PayU\Drivers\RoDriver;
use WeBRTeu\PayU\Exceptions\PayUException;

/**
 * Gateway-ul `payu`, care deleagă către driverul din PAYU_DRIVER (payu-gpo | payu-ro).
 */
class PayUGateway implements Driver
{
    public function __construct(private readonly Container $container) {}

    public function driver(): Driver
    {
        $driver = Config::string('payu.driver', 'payu-gpo');

        return match ($driver) {
            'payu-gpo' => $this->container->make(GpoDriver::class),
            'payu-ro' => $this->container->make(RoDriver::class),
            default => throw new PayUException(sprintf('Driver PayU necunoscut [%s]: folosește payu-gpo sau payu-ro.', $driver)),
        };
    }

    public function name(): string
    {
        return 'payu';
    }

    public function createPayment(PaymentRequestData $request): PaymentRedirectData
    {
        return $this->driver()->createPayment($request);
    }

    public function parseWebhook(Request $request): WebhookData
    {
        return $this->driver()->parseWebhook($request);
    }

    public function webhookResponse(?WebhookData $webhook, bool $accepted): Response
    {
        return $this->driver()->webhookResponse($webhook, $accepted);
    }

    public function status(string $providerPaymentId): PaymentStatusData
    {
        return $this->driver()->status($providerPaymentId);
    }

    public function refund(string $providerPaymentId, int $amount): RefundData
    {
        return $this->driver()->refund($providerPaymentId, $amount);
    }

    public function capture(string $providerPaymentId, int $amount): PaymentStatusData
    {
        return $this->driver()->capture($providerPaymentId, $amount);
    }

    public function void(string $providerPaymentId): PaymentStatusData
    {
        return $this->driver()->void($providerPaymentId);
    }
}
