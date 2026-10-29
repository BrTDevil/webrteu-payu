<?php

declare(strict_types=1);

namespace WeBRTeu\PayU\Ro;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use WeBRTeu\Payments\Support\Redactor;
use WeBRTeu\PayU\Exceptions\PayUException;

/**
 * HTTP brut spre PayU România API v4, cu X-Header-Merchant / X-Header-Date / X-Header-Signature. Fără retry.
 */
class RoClient
{
    /**
     * @param  array<string, mixed>  $body
     * @param  array<string, scalar>  $query
     * @return array<array-key, mixed>
     */
    public function send(string $method, string $path, array $body = [], array $query = []): array
    {
        $json = $body === [] ? '' : (string) json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $queryString = http_build_query($query);
        $date = CarbonImmutable::now()->toIso8601String();
        $merchant = Config::string('payu.ro.merchant', '');

        $request = Http::acceptJson()
            ->timeout(Config::integer('payu.timeout', 20))
            ->withHeaders([
                'X-Header-Merchant' => $merchant,
                'X-Header-Date' => $date,
                'X-Header-Signature' => RequestSigner::sign($merchant, $date, $method, $path, $queryString, $json, Config::string('payu.ro.secret', '')),
            ]);

        if ($json !== '') {
            $request = $request->withBody($json, 'application/json');
        }

        $url = rtrim(Config::string('payu.ro.base_url', 'https://secure.payu.ro'), '/').$path.($queryString !== '' ? '?'.$queryString : '');
        $response = $request->send($method, $url);
        $decoded = $response->json();
        $decoded = is_array($decoded) ? $decoded : [];

        if ($response->failed()) {
            Log::warning('payu.request_failed', ['driver' => 'payu-ro', 'path' => $path, 'status' => $response->status(), 'response' => $this->redact($decoded)]);

            throw PayUException::fromResponse($path, $response->status(), $decoded);
        }

        return $decoded;
    }

    /**
     * @param  array<array-key, mixed>  $payload
     * @return array<array-key, mixed>
     */
    public function redact(array $payload): array
    {
        return Redactor::redact($payload);
    }
}
