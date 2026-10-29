<?php

declare(strict_types=1);

namespace WeBRTeu\PayU\Gpo;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use WeBRTeu\Payments\Support\Redactor;
use WeBRTeu\PayU\Exceptions\PayUException;

/**
 * HTTP brut spre PayU GPO (OpenPayU v2.1): OAuth2 client_credentials cu token în cache (expires_in - 60),
 * fără urmărirea redirect-ului 302 de la crearea comenzii. Fără retry.
 */
class GpoClient
{
    public const SANDBOX_URL = 'https://secure.snd.payu.com';

    public const LIVE_URL = 'https://secure.payu.com';

    public function baseUrl(): string
    {
        $configured = Config::get('payu.gpo.base_url');

        if (is_string($configured) && $configured !== '') {
            return rtrim($configured, '/');
        }

        return Config::boolean('payu.sandbox', true) ? self::SANDBOX_URL : self::LIVE_URL;
    }

    public function tokenKey(): string
    {
        return 'payu:gpo:token:'.Config::string('payu.gpo.client_id', '');
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<array-key, mixed>
     */
    public function send(string $method, string $path, array $body = []): array
    {
        $request = Http::acceptJson()
            ->withToken($this->token())
            ->withoutRedirecting()
            ->timeout(Config::integer('payu.timeout', 20));

        $response = $body === [] ? $request->send($method, $this->baseUrl().$path) : $request->asJson()->send($method, $this->baseUrl().$path, ['json' => $body]);

        return $this->decode($path, $response);
    }

    public function token(): string
    {
        $token = Cache::get($this->tokenKey());

        if (is_string($token) && $token !== '') {
            return $token;
        }

        $response = Http::asForm()->acceptJson()->timeout(Config::integer('payu.timeout', 20))->post($this->baseUrl().'/pl/standard/user/oauth/authorize', [
            'grant_type' => 'client_credentials',
            'client_id' => Config::string('payu.gpo.client_id', ''),
            'client_secret' => Config::string('payu.gpo.client_secret', ''),
        ]);

        $json = $this->decode('/pl/standard/user/oauth/authorize', $response);
        $token = $json['access_token'] ?? null;

        if (! is_string($token) || $token === '') {
            throw new PayUException('PayU GPO nu a întors un access_token: verifică PAYU_GPO_CLIENT_ID / PAYU_GPO_CLIENT_SECRET.');
        }

        $ttl = is_numeric($json['expires_in'] ?? null) ? (int) $json['expires_in'] - 60 : 600;
        Cache::put($this->tokenKey(), $token, max(60, $ttl));

        return $token;
    }

    /**
     * @param  array<array-key, mixed>  $payload
     * @return array<array-key, mixed>
     */
    public function redact(array $payload): array
    {
        return Redactor::redact($payload, ['access_token']);
    }

    /**
     * @return array<array-key, mixed>
     */
    private function decode(string $path, Response $response): array
    {
        $json = $response->json();
        $json = is_array($json) ? $json : [];

        // 302 la crearea comenzii e succes: corpul conține redirectUri.
        if ($response->failed() || ($response->status() >= 300 && $response->status() !== 302)) {
            if ($response->status() === 401) {
                Cache::forget($this->tokenKey());
            }

            Log::warning('payu.request_failed', ['driver' => 'payu-gpo', 'path' => $path, 'status' => $response->status(), 'response' => $this->redact($json)]);

            throw PayUException::fromResponse($path, $response->status(), $json);
        }

        return $json;
    }
}
