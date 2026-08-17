<?php

declare(strict_types=1);

namespace Gsebastiao\LaravelMenu\Tests;

use Gsebastiao\LaravelMenu\MenuServiceProvider;
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
        $providers = [MenuServiceProvider::class];

        // O Testbench não faz auto-discovery: quando o pacote de auditoria
        // está instalado, é registado aqui para os testes de integração.
        if (class_exists(\Gsebastiao\Auditable\AuditableServiceProvider::class)) {
            array_unshift($providers, \Gsebastiao\Auditable\AuditableServiceProvider::class);
        }

        return $providers;
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

        // Cache em array nos testes (rápida e isolada).
        $config->set('cache.default', 'array');
        $config->set('menu.permission_mode', 'none');
    }
}
