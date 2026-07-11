<?php

declare(strict_types=1);

namespace Gsebastiao\DynamicMenu;

use Gsebastiao\DynamicMenu\Console\Commands\RebuildMenuCacheCommand;
use Gsebastiao\DynamicMenu\Database\Seeders\MenuItemsSeeder;
use Gsebastiao\DynamicMenu\Http\Middleware\VerifyMenuPermission;
use Gsebastiao\DynamicMenu\Services\MenuManager;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;

class DynamicMenuServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/dynamic-menu.php',
            'dynamic-menu'
        );

        $this->app->singleton(MenuManager::class, fn () => new MenuManager());

        // Alias legível para injeção/resolução.
        $this->app->alias(MenuManager::class, 'dynamic-menu');
    }

    public function boot(Router $router): void
    {
        // Migrations carregadas diretamente (funcionam mesmo sem publicar).
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        // Regista o alias de middleware para uso em rotas.
        $router->aliasMiddleware('menu.permission', VerifyMenuPermission::class);

        if ($this->app->runningInConsole()) {
            $this->registerPublishing();

            $this->commands([
                RebuildMenuCacheCommand::class,
            ]);
        }
    }

    protected function registerPublishing(): void
    {
        // Config
        $this->publishes([
            __DIR__.'/../config/dynamic-menu.php' => config_path('dynamic-menu.php'),
        ], 'dynamic-menu-config');

        // Migrations (cópia opcional, caso o dev queira versionar/editar)
        $this->publishes([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], 'dynamic-menu-migrations');

        // Seeder
        $this->publishes([
            __DIR__.'/../database/seeders/MenuItemsSeeder.php' => database_path('seeders/MenuItemsSeeder.php'),
        ], 'dynamic-menu-seeders');
    }
}
