<?php

declare(strict_types=1);

namespace WeBRTeu\PayU;

use Illuminate\Contracts\Container\Container;
use Illuminate\Support\ServiceProvider;
use WeBRTeu\Payments\PaymentManager;

class PayUServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/payu.php', 'payu');

        $this->app->singleton(Ro\RoClient::class);
        $this->app->singleton(Gpo\GpoClient::class);
        $this->app->singleton(PayUGateway::class);

        $this->callAfterResolving(PaymentManager::class, function (PaymentManager $payments): void {
            $payments->extend('payu', fn (Container $app): PayUGateway => $app->make(PayUGateway::class));
        });
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/payu.php' => $this->app->configPath('payu.php'),
        ], 'payu-config');
    }
}
