<?php

declare(strict_types=1);

use Gsebastiao\DynamicMenu\Models\MenuItem;
use Gsebastiao\DynamicMenu\Services\MenuManager;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

function manager(): MenuManager
{
    return app(MenuManager::class);
}

it('monta a árvore aninhada em N níveis', function () {
    $root = MenuItem::create(['name' => 'root', 'label' => 'Root', 'order' => 1]);
    $mid  = MenuItem::create(['name' => 'mid', 'label' => 'Mid', 'parent_id' => $root->id, 'order' => 1]);
    MenuItem::create(['name' => 'leaf', 'label' => 'Leaf', 'parent_id' => $mid->id, 'order' => 1]);

    $tree = manager()->tree();

    expect($tree)->toHaveCount(1)
        ->and($tree->first()['children'])->toHaveCount(1)
        ->and($tree->first()['children'][0]['children'])->toHaveCount(1)
        ->and($tree->first()['children'][0]['children'][0]['name'])->toBe('leaf');
});

it('respeita a ordenação por order', function () {
    MenuItem::create(['name' => 'b', 'label' => 'B', 'order' => 2]);
    MenuItem::create(['name' => 'a', 'label' => 'A', 'order' => 1]);

    $tree = manager()->tree();

    expect($tree->pluck('name')->all())->toBe(['a', 'b']);
});

it('guarda a árvore em cache e invalida ao gravar', function () {
    config()->set('dynamic-menu.cache.enabled', true);

    MenuItem::create(['name' => 'a', 'label' => 'A']);

    // Primeira leitura -> popula cache
    manager()->tree();
    expect(Cache::store('array')->has('dynamic_menu:tree'))->toBeTrue();

    // Novo item deve invalidar a cache automaticamente (evento saved)
    MenuItem::create(['name' => 'b', 'label' => 'B']);
    expect(Cache::store('array')->has('dynamic_menu:tree'))->toBeFalse();

    // Releitura reflete o novo item
    expect(manager()->tree())->toHaveCount(2);
});

it('filtra a árvore pelas permissões do utilizador no modo string', function () {
    config()->set('dynamic-menu.permission_mode', 'string');

    MenuItem::create(['name' => 'public', 'label' => 'Público']);
    MenuItem::create(['name' => 'secret', 'label' => 'Secreto', 'permission' => 'secret.view']);

    $semPerm = manager()->forUser(null, []);
    expect($semPerm->pluck('name')->all())->toBe(['public']);

    $comPerm = manager()->forUser(null, ['secret.view']);
    expect($comPerm->pluck('name')->all())->toBe(['public', 'secret']);
});

it('mantém o pai visível se tiver filhos visíveis', function () {
    config()->set('dynamic-menu.permission_mode', 'string');

    // Pai exige permissão que o user não tem, mas o filho é permitido.
    $parent = MenuItem::create(['name' => 'parent', 'label' => 'Pai', 'permission' => 'pai.only']);
    MenuItem::create(['name' => 'child', 'label' => 'Filho', 'parent_id' => $parent->id, 'permission' => 'filho.ok']);

    $tree = manager()->forUser(null, ['filho.ok']);

    expect($tree)->toHaveCount(1)
        ->and($tree->first()['name'])->toBe('parent')
        ->and($tree->first()['children'])->toHaveCount(1);
});

it('resolve label de permissão no modo id contra a tabela configurada', function () {
    // Cria uma tabela de permissões arbitrária do "projeto".
    Schema::create('permissions', function ($table) {
        $table->id();
        $table->string('name');
    });

    $permId = \Illuminate\Support\Facades\DB::table('permissions')
        ->insertGetId(['name' => 'gerir.tudo']);

    config()->set('dynamic-menu.permission_mode', 'id');
    config()->set('dynamic-menu.resolver', [
        'table' => 'permissions', 'key' => 'id', 'column' => 'name',
    ]);

    $item = MenuItem::create([
        'name'       => 'a',
        'label'      => 'A',
        'permission' => (string) $permId,
    ]);

    expect($item->resolvedPermissionLabel())->toBe('gerir.tudo')
        ->and($item->isVisibleTo([$permId]))->toBeTrue()
        ->and($item->isVisibleTo([999]))->toBeFalse();
});

it('o comando artisan reconstrói a cache', function () {
    MenuItem::create(['name' => 'a', 'label' => 'A']);

    $this->artisan('dynamic-menu:cache')
        ->expectsOutputToContain('Cache de menus reconstruída')
        ->assertSuccessful();
});

it('o comando artisan --flush limpa a cache', function () {
    MenuItem::create(['name' => 'a', 'label' => 'A']);
    manager()->tree();

    $this->artisan('dynamic-menu:cache', ['--flush' => true])
        ->expectsOutputToContain('limpa com sucesso')
        ->assertSuccessful();

    expect(Cache::store('array')->has('dynamic_menu:tree'))->toBeFalse();
});
