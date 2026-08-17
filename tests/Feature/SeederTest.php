<?php

declare(strict_types=1);

use Gsebastiao\LaravelMenu\Database\Seeders\MenuItemsSeeder;
use Gsebastiao\LaravelMenu\MenuServiceProvider;
use Gsebastiao\LaravelMenu\Models\MenuItem;
use Illuminate\Support\ServiceProvider;

it('cria o menu de exemplo com vários níveis', function () {
    $this->seed(MenuItemsSeeder::class);

    $tree = app(\Gsebastiao\LaravelMenu\Services\MenuManager::class)->tree();

    expect($tree->pluck('name')->all())->toBe(['dashboard', 'admin', 'docs'])
        ->and(collect($tree[1]['children'])->pluck('name')->all())
        ->toBe(['admin.users', 'admin.roles', 'admin.sep.1', 'admin.settings'])
        ->and($tree[1]['children'][3]['children'][0]['name'])->toBe('admin.settings.system');
});

it('pode ser corrido várias vezes sem duplicar itens', function () {
    $this->seed(MenuItemsSeeder::class);
    $this->seed(MenuItemsSeeder::class);

    expect(MenuItem::count())->toBe(8);
});

it('publica o seeder com o namespace Database\Seeders', function () {
    $paths = ServiceProvider::pathsToPublish(MenuServiceProvider::class, 'laravel-menu-seeders');

    expect($paths)->toHaveCount(1)
        ->and(array_key_first($paths))->toEndWith('stubs/MenuItemsSeeder.php.stub')
        ->and(array_values($paths)[0])->toEndWith('database/seeders/MenuItemsSeeder.php')
        ->and(file_get_contents(array_key_first($paths)))->toContain("namespace Database\\Seeders;\n");
});

it('mantém o stub publicado igual ao seeder do pacote (exceto o namespace)', function () {
    $stub = file_get_contents(__DIR__.'/../../stubs/MenuItemsSeeder.php.stub');
    $classe = file_get_contents(__DIR__.'/../../database/seeders/MenuItemsSeeder.php');

    expect(str_replace('namespace Database\Seeders;', 'namespace Gsebastiao\LaravelMenu\Database\Seeders;', $stub))
        ->toBe($classe);
});
