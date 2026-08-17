<?php

declare(strict_types=1);

use Gsebastiao\LaravelMenu\Facades\Menu;
use Gsebastiao\LaravelMenu\Models\MenuItem;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    Route::get('/utilizadores', fn () => 'ok')->name('users.index');
    app('router')->getRoutes()->refreshNameLookups();
});

it('desenha o menu do utilizador com <x-laravel-menu::menu />', function () {
    $admin = MenuItem::create(['name' => 'admin', 'label' => 'Administração', 'icon' => 'bi bi-shield', 'order' => 1]);
    MenuItem::create(['name' => 'users', 'label' => 'Utilizadores', 'route' => 'users.index', 'parent_id' => $admin->id, 'badge' => 'novo', 'order' => 1]);
    MenuItem::create(['name' => 'sep', 'label' => '—', 'parent_id' => $admin->id, 'is_separator' => true, 'order' => 2]);
    MenuItem::create(['name' => 'relatorios', 'label' => 'Relatórios', 'route' => 'reports.index', 'parent_id' => $admin->id, 'order' => 3]);
    MenuItem::create(['name' => 'docs', 'label' => 'Documentação', 'route' => 'https://exemplo.com', 'target' => '_blank', 'order' => 2]);

    $html = (string) $this->blade('<x-laravel-menu::menu />');

    expect($html)
        ->toContain('<ul class="menu">')
        ->toContain('<ul class="menu menu-submenu">')
        ->toContain('<i class="menu-icon bi bi-shield" aria-hidden="true"></i>')
        ->toContain('href="http://localhost/utilizadores"')
        ->toContain('<span class="menu-badge">novo</span>')
        ->toContain('<li class="menu-separator" role="separator"></li>')
        ->toContain('target="_blank"')
        ->toContain('rel="noopener noreferrer"')
        ->toContain('Relatórios')
        ->not->toContain('href="#"');

    // Rota inexistente (reports.index): o item aparece, mas sem link.
    expect(preg_match('#<a class="menu-link"\s*>\s*<span class="menu-label">Relatórios#', $html))->toBe(1);
});

it('marca o item da página atual com is-active', function () {
    Route::get('/pagina', fn () => Blade::render('<x-laravel-menu::menu />'))->name('pagina');
    app('router')->getRoutes()->refreshNameLookups();

    MenuItem::create(['name' => 'users', 'label' => 'Utilizadores', 'route' => 'users.index']);
    MenuItem::create(['name' => 'pagina', 'label' => 'Página', 'route' => 'pagina']);

    $this->blade('<x-laravel-menu::menu />')->assertDontSee('is-active');

    $this->get('/pagina')->assertSee('<li class="menu-item is-active">', false);
});

it('mostra só os itens que o utilizador pode ver', function () {
    config()->set('menu.permission_mode', 'string');
    Menu::resolvePermissionsUsing(fn () => ['users.view']);

    MenuItem::create(['name' => 'users', 'label' => 'Utilizadores', 'permission' => 'users.view']);
    MenuItem::create(['name' => 'config', 'label' => 'Configurações', 'permission' => 'settings.view']);

    $this->blade('<x-laravel-menu::menu />')
        ->assertSee('Utilizadores')
        ->assertDontSee('Configurações');
});

it('aceita itens próprios e atributos para a <ul>', function () {
    $itens = [
        ['name' => 'a', 'label' => 'Início', 'route' => '/', 'children' => []],
    ];

    $this->blade('<x-laravel-menu::menu :items="$itens" class="nav" id="principal" />', ['itens' => $itens])
        ->assertSee('<ul class="menu nav" id="principal">', false)
        ->assertSee('href="http://localhost"', false);
});

it('escapa o texto dos itens', function () {
    MenuItem::create(['name' => 'x', 'label' => '<script>alert(1)</script>']);

    $this->blade('<x-laravel-menu::menu />')
        ->assertDontSee('<script>alert(1)</script>', false)
        ->assertSee('&lt;script&gt;', false);
});

it('não desenha nada quando o menu está vazio', function () {
    expect(trim((string) $this->blade('<x-laravel-menu::menu />')))->toBe('');
});

it('não é afetado por uma variável $items da página', function () {
    MenuItem::create(['name' => 'menu', 'label' => 'Item do menu']);

    $this->blade('<x-laravel-menu::menu />', ['items' => collect([['label' => 'Produto da página']])])
        ->assertSee('Item do menu')
        ->assertDontSee('Produto da página');
});
