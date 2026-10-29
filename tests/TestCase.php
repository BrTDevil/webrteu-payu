<?php

declare(strict_types=1);

namespace WeBRTeu\PayU\Tests;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\URL;
use Orchestra\Testbench\TestCase as Orchestra;
use WeBRTeu\Payments\PaymentsServiceProvider;
use WeBRTeu\PayU\PayUServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        // URL-urile generate (notifyUrl, BACKREF...) folosesc app.url, nu host-ul cererii de test.
        URL::forceRootUrl('https://shop.example');
        URL::forceScheme('https');
    }

    /**
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [PaymentsServiceProvider::class, PayUServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
        $app['config']->set('app.url', 'https://shop.example');
        $app['config']->set('payu.ro.merchant', 'MERCH1');
        $app['config']->set('payu.ro.secret', 'ro-secret');
        $app['config']->set('payu.gpo.pos_id', '300746');
        $app['config']->set('payu.gpo.second_key', 'b6ca15b0d1020e8094d9b5f8d163db54');
        $app['config']->set('payu.gpo.client_id', '300746');
        $app['config']->set('payu.gpo.client_secret', 'gpo-client-secret');
    }

    protected function defineDatabaseMigrations(): void
    {
        /** @var Migration $migration */
        $migration = require __DIR__.'/../vendor/webrteu/payments/database/migrations/create_payments_tables.php.stub';
        $migration->up();
    }
}
