<?php

declare(strict_types=1);

namespace Gsebastiao\LaravelMenu;

use Gsebastiao\LaravelMenu\Console\Commands\RebuildMenuCacheCommand;
use Gsebastiao\LaravelMenu\Http\Middleware\VerifyMenuPermission;
use Gsebastiao\LaravelMenu\Services\MenuManager;
use Gsebastiao\LaravelMenu\Support\AuditingSupport;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;

class MenuServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/menu.php', 'menu');

        $this->app->singleton(MenuManager::class);

        // Alias legível para injeção/resolução: app('laravel-menu').
        $this->app->alias(MenuManager::class, 'laravel-menu');
    }

    public function boot(Router $router): void
    {
        // Auditoria ligada sem o pacote de auditoria instalado? Falha já, com
        // uma mensagem que diz o que fazer, em vez de não auditar nada.
        AuditingSupport::assertConfigured();

        // A migration corre com `php artisan migrate`, mesmo sem a publicar.
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        // Componente pronto a usar: <x-laravel-menu::menu />
        // (resources/views/components/menu.blade.php)
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'laravel-menu');

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
        $this->publishes([
            __DIR__.'/../config/menu.php' => config_path('menu.php'),
        ], 'laravel-menu-config');

        $this->publishes([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], 'laravel-menu-migrations');

        // Publicado a partir de um stub com o namespace Database\Seeders, para
        // funcionar com `php artisan db:seed --class=MenuItemsSeeder`.
        $this->publishes([
            __DIR__.'/../stubs/MenuItemsSeeder.php.stub' => database_path('seeders/MenuItemsSeeder.php'),
        ], 'laravel-menu-seeders');

        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/laravel-menu'),
        ], 'laravel-menu-views');
    }
}
