<?php

declare(strict_types=1);

namespace Gsebastiao\LaravelMenu;

use Gsebastiao\LaravelMenu\Console\Commands\RebuildMenuCacheCommand;
use Gsebastiao\LaravelMenu\Database\Seeders\MenuItemsSeeder;
use Gsebastiao\LaravelMenu\Http\Middleware\VerifyMenuPermission;
use Gsebastiao\LaravelMenu\Services\MenuManager;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;

class MenuServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/menu.php', 'menu');

        $this->app->singleton(MenuManager::class, fn() => new MenuManager());

        // Alias legível para injeção/resolução.
        $this->app->alias(MenuManager::class, 'laravel-menu');
    }

    public function boot(Router $router): void
    {
        // Migrations carregadas diretamente (funcionam mesmo sem publicar).
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

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
            __DIR__ . '/../config/menu.php' => config_path('menu.php'),
        ], 'laravel-menu-config');

        // Migrations (cópia opcional, caso o dev queira versionar/editar)
        $this->publishes([
            __DIR__ . '/../database/migrations' => database_path('migrations'),
        ], 'laravel-menu-migrations');

        // Seeder
        $this->publishes([
            __DIR__ . '/../database/seeders/MenuItemsSeeder.php' => database_path('seeders/MenuItemsSeeder.php'),
        ], 'laravel-menu-seeders');
    }
}
