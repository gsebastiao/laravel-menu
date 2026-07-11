<?php

declare(strict_types=1);

namespace Gsebastiao\DynamicMenu\Tests;

use Gsebastiao\DynamicMenu\DynamicMenuServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }

    protected function getPackageProviders($app): array
    {
        return [
            DynamicMenuServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $config = $app['config'];

        $config->set('database.default', 'testing');
        $config->set('database.connections.testing', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);

        // Cache em array por omissão nos testes (rápido e isolado).
        $config->set('cache.default', 'array');
        $config->set('dynamic-menu.permission_mode', 'none');
    }
}
