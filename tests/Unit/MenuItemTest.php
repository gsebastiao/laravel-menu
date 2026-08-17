<?php

declare(strict_types=1);

use Gsebastiao\LaravelMenu\Models\MenuItem;

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

it('constrói relações de árvore com filhos', function () {
    $root = MenuItem::create(['name' => 'root', 'label' => 'Root']);
    $child = MenuItem::create(['name' => 'child', 'label' => 'Child', 'parent_id' => $root->id]);

    expect($root->children)->toHaveCount(1)
        ->and($root->children->first()->id)->toBe($child->id);
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

it('no modo none todos os itens são visíveis', function () {
    config()->set('menu.permission_mode', 'none');

    $item = MenuItem::create(['name' => 'a', 'label' => 'A', 'permission' => 'qualquer.coisa']);

    expect($item->isVisibleTo([]))->toBeTrue();
});

it('no modo string filtra por permissão textual', function () {
    config()->set('menu.permission_mode', 'string');

    $item = MenuItem::create(['name' => 'a', 'label' => 'A', 'permission' => 'user.create']);

    expect($item->isVisibleTo(['user.create']))->toBeTrue()
        ->and($item->isVisibleTo(['user.delete']))->toBeFalse();
});

it('itens sem permissão passam mesmo no modo string', function () {
    config()->set('menu.permission_mode', 'string');

    $item = MenuItem::create(['name' => 'a', 'label' => 'A', 'permission' => null]);

    expect($item->isVisibleTo([]))->toBeTrue();
});
