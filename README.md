# webrteu/payu

[![PHP](https://img.shields.io/badge/PHP-%5E8.2-777bb4)](https://www.php.net/)
[![Laravel](https://img.shields.io/badge/Laravel-12%E2%80%9313-ff2d20)](https://laravel.com/)
[![Licență MIT](https://img.shields.io/badge/licen%C8%9B%C4%83-MIT-green)](LICENSE.md)
[![Packagist](https://img.shields.io/packagist/v/webrteu/payu)](https://packagist.org/packages/webrteu/payu)

Integrare Laravel pentru PayU în România, cu **două drivere** sub același gateway `payu`: `payu-ro` (PayU România,
API v4) și `payu-gpo` (PayU GPO Europe, OpenPayU v2.1). Implementează `Gateway`, `Refundable` și `Capturable` din
`webrteu/payments`. Nu folosește ALU / LiveUpdate (deprecated).

Nu este un pachet oficial PayU.

## Care platformă se aplică

Conform PayU: integrările începute **înainte de 4 noiembrie 2024** sau cu volum anual peste **15.000.000 RON** merg pe
PayU România (`payu-ro`); celelalte, pe **PayU GPO Europe** (`payu-gpo`). **Întreabă PayU pe ce platformă e contul.**

## Cerințe

- PHP `^8.2`, Laravel 12–13
- cont PayU (RO sau GPO)
- `webrteu/payments`

## Instalare

```bash
composer require webrteu/payu
php artisan vendor:publish --tag=payu-config
```

## Configurare

```dotenv
PAYU_DRIVER=payu-gpo        # payu-gpo (conturi noi) | payu-ro (conturi vechi / volum mare). Întreabă PayU
PAYU_SANDBOX=true

# payu-ro (API v4)
PAYU_RO_MERCHANT=           # cod merchant
PAYU_RO_SECRET=
PAYU_RO_PAYMENT_METHOD=CCVISAMC

# payu-gpo
PAYU_GPO_POS_ID=
PAYU_GPO_SECOND_KEY=        # cheia MD5 („second key”) pentru notificări
PAYU_GPO_CLIENT_ID=
PAYU_GPO_CLIENT_SECRET=
PAYU_GPO_BASE_URL=          # gol = secure.snd.payu.com (sandbox) / secure.payu.com

PAYU_TIMEOUT=20
PAYU_NOTIFY_URL=            # gol = https://site.ro/webhooks/payments/payu
```

## Utilizare

```php
$redirect = Payments::start('payu', new PaymentRequestData(
    orderId: "{$order->id}-{$attempt}",   // extOrderId / merchantPaymentReference, unic per încercare
    amount: 14170,
    description: "Comanda #{$order->id}",
    customer: new CustomerData($order->email, $order->first_name, $order->last_name, $order->phone),
    returnUrl: route('checkout.payu.return', $order),
    ip: $request->ip(),
), payable: $order);

return $redirect->toResponse();
```

| Operație | `payu-ro` (v4) | `payu-gpo` |
|---|---|---|
| pornire | `POST /api/v4/payments/authorize` → `paymentResult.url` | `POST /api/v2_1/orders` → `redirectUri` (302 **neurmat**) |
| status | `GET /api/v4/payments/status/{merchantPaymentReference}` | `GET /api/v2_1/orders/{orderId}` |
| refund | `POST /api/v4/payments/refund` | `POST /api/v2_1/orders/{orderId}/refunds` |
| captură | `POST /api/v4/payments/capture` | `PUT /api/v2_1/orders/{orderId}/status` (`COMPLETED`) |
| anulare | refund pe suma integrală ⚠️ | `DELETE /api/v2_1/orders/{orderId}` |

Acces direct: `PayU::refund($id, $bani)`, `PayU::capture(...)`, `PayU::void(...)`, `PayU::driver()`.

⚠️ `payu-ro`: schema semnăturii (`HMAC-SHA256(merchant + date + method + path + md5(query) + md5(body))`), câmpurile v4
și formatul răspunsului la notificare se confirmă din `secure.payu.ro/docs`. Pachetul răspunde cu HTTP 200 gol.

## Webhook

- `payu-gpo`: `notifyUrl` se trimite automat: `https://site.ro/webhooks/payments/payu`. Verificare
  `OpenPayu-Signature` (MD5 sau SHA-256 pe `corp + second key`); răspuns HTTP 200 fără corp
- `payu-ro`: același URL, setat în panoul PayU; verificare HMAC pe corpul brut

Notificările pot veni în altă ordine (ex. `PENDING` după `COMPLETED`): statusul nu coboară niciodată.

## Statusuri

| `payu-gpo` | `payu-ro` ⚠️ | `PaymentStatus` |
|---|---|---|
| `NEW`, `PENDING` | `PENDING`, `IN_PROGRESS` | `Pending` |
| `WAITING_FOR_CONFIRMATION` | `PAYMENT_AUTHORIZED` | `Authorized` |
| `COMPLETED` | `SUCCESS`, `COMPLETE` | `Paid` |
| `CANCELED` | `CANCELED` | `Cancelled` |
| refund `FINALIZED` | `REFUND`, `REVERSED` | `Refunded` |
| | `FAILED`, `DECLINED` | `Failed` |

## Ce nu face pachetul

- nu folosește ALU / LiveUpdate (deprecated)
- nu colectează date de card
- nu decide singur platforma: `PAYU_DRIVER` se setează după confirmarea PayU

## Testare

```bash
composer test
PAYU_GPO_SANDBOX_POS_ID=... PAYU_GPO_SANDBOX_CLIENT_ID=... PAYU_GPO_SANDBOX_CLIENT_SECRET=... vendor/bin/pest --group=sandbox
```

Fixture-urile sunt după documentație. **De înlocuit cu răspunsuri reale**.

## Changelog

Vezi [CHANGELOG.md](CHANGELOG.md).

## Autor

[WeBRTeu](https://webrt.eu)

## Licență

MIT. Vezi [LICENSE.md](LICENSE.md).
