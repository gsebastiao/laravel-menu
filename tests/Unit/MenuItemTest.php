<?php

declare(strict_types=1);

use Gsebastiao\LaravelMenu\Models\MenuItem;
use Gsebastiao\LaravelMenu\Tests\Fixtures\MenuItemComTabela;

it('casta params para array e booleans corretamente', function () {
    $item = MenuItem::create([
        'name'   => 'x',
        'label'  => 'X',
        'params' => ['a' => 1, 'b' => 'dois'],
    ]);

    expect($item->fresh()->params)->toBe(['a' => 1, 'b' => 'dois'])
        ->and($item->is_active)->toBeTrue()
        ->and($item->is_separator)->toBeFalse();
});

it('aplica os valores padrão logo após o create, sem precisar de fresh()', function () {
    $item = MenuItem::create(['name' => 'x', 'label' => 'X']);

    expect($item->is_active)->toBeTrue()
        ->and($item->is_separator)->toBeFalse()
        ->and($item->order)->toBe(0)
        ->and($item->isVisibleTo([]))->toBeTrue();
});

it('constrói relações de árvore com filhos', function () {
    $root = MenuItem::create(['name' => 'root', 'label' => 'Root']);
    $child = MenuItem::create(['name' => 'child', 'label' => 'Child', 'parent_id' => $root->id]);

    expect($root->children)->toHaveCount(1)
        ->and($root->children->first()->id)->toBe($child->id)
        ->and($child->parent->id)->toBe($root->id);
});

it('ordena os filhos por order e depois por id, como a árvore', function () {
    $root = MenuItem::create(['name' => 'root', 'label' => 'Root']);
    MenuItem::create(['name' => 'b', 'label' => 'B', 'parent_id' => $root->id, 'order' => 1]);
    MenuItem::create(['name' => 'c', 'label' => 'C', 'parent_id' => $root->id, 'order' => 1]);
    MenuItem::create(['name' => 'a', 'label' => 'A', 'parent_id' => $root->id, 'order' => 0]);

    expect($root->children->pluck('name')->all())->toBe(['a', 'b', 'c']);
});

it('aplica o scope active', function () {
    MenuItem::create(['name' => 'on', 'label' => 'On', 'is_active' => true]);
    MenuItem::create(['name' => 'off', 'label' => 'Off', 'is_active' => false]);

    expect(MenuItem::query()->active()->count())->toBe(1);
});

it('suporta soft deletes', function () {
    $item = MenuItem::create(['name' => 'del', 'label' => 'Del']);
    $item->delete();

    expect(MenuItem::query()->count())->toBe(0)
        ->and(MenuItem::withTrashed()->count())->toBe(1);
});

it('usa a tabela da config, a menos que uma subclasse defina $table', function () {
    config()->set('menu.table', 'itens_da_config');

    expect((new MenuItem())->getTable())->toBe('itens_da_config')
        ->and((new MenuItemComTabela())->getTable())->toBe('itens_personalizados');
});

it('no modo none todos os itens são visíveis', function () {
    config()->set('menu.permission_mode', 'none');

    $item = MenuItem::create(['name' => 'a', 'label' => 'A', 'permission' => 'qualquer.coisa']);

    expect($item->isVisibleTo([]))->toBeTrue();
});

it('no modo string filtra por permissão textual', function () {
    config()->set('menu.permission_mode', 'string');

    $item = MenuItem::create(['name' => 'a', 'label' => 'A', 'permission' => 'user.create']);

    expect($item->isVisibleTo(['user.create']))->toBeTrue()
        ->and($item->isVisibleTo(collect(['user.create'])))->toBeTrue()
        ->and($item->isVisibleTo(['user.delete']))->toBeFalse();
});

it('itens sem permissão passam mesmo no modo string', function () {
    config()->set('menu.permission_mode', 'string');

    $item = MenuItem::create(['name' => 'a', 'label' => 'A', 'permission' => null]);

    expect($item->isVisibleTo([]))->toBeTrue();
});

it('um item inativo nunca é visível', function () {
    $item = MenuItem::create(['name' => 'a', 'label' => 'A', 'is_active' => false]);

    expect($item->isVisibleTo([]))->toBeFalse();
});
