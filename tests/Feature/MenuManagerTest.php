<?php

declare(strict_types=1);

use Gsebastiao\LaravelMenu\Facades\Menu;
use Gsebastiao\LaravelMenu\Models\MenuItem;
use Gsebastiao\LaravelMenu\Services\MenuManager;
use Gsebastiao\LaravelMenu\Tests\Fixtures\MenuPermissions;
use Gsebastiao\LaravelMenu\Tests\Fixtures\Permissao;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

function manager(): MenuManager
{
    return app(MenuManager::class);
}

/**
 * Nomes da árvore, com os filhos entre parênteses: "admin(users,roles)".
 */
function nomes(iterable $nodes): string
{
    return collect($nodes)
        ->map(fn (array $node) => $node['name'].($node['children'] ? '('.nomes($node['children']).')' : ''))
        ->implode(',');
}

/*
|--------------------------------------------------------------------------
| Árvore
|--------------------------------------------------------------------------
*/

it('monta a árvore aninhada em N níveis', function () {
    $root = MenuItem::create(['name' => 'root', 'label' => 'Root', 'order' => 1]);
    $mid = MenuItem::create(['name' => 'mid', 'label' => 'Mid', 'parent_id' => $root->id, 'order' => 1]);
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

    expect(manager()->tree()->pluck('name')->all())->toBe(['a', 'b']);
});

it('esconde os filhos de um item inativo ou apagado', function () {
    $off = MenuItem::create(['name' => 'off', 'label' => 'Off', 'is_active' => false]);
    MenuItem::create(['name' => 'filho-off', 'label' => 'F', 'parent_id' => $off->id]);

    $del = MenuItem::create(['name' => 'del', 'label' => 'Del']);
    MenuItem::create(['name' => 'filho-del', 'label' => 'F', 'parent_id' => $del->id]);
    $del->delete();

    MenuItem::create(['name' => 'on', 'label' => 'On']);

    expect(nomes(manager()->tree()))->toBe('on');
});

/*
|--------------------------------------------------------------------------
| Filtragem por permissões (forUser)
|--------------------------------------------------------------------------
*/

it('filtra a árvore pelas permissões do utilizador no modo string', function () {
    config()->set('menu.permission_mode', 'string');

    MenuItem::create(['name' => 'public', 'label' => 'Público']);
    MenuItem::create(['name' => 'secret', 'label' => 'Secreto', 'permission' => 'secret.view']);

    expect(manager()->forUser(null, [])->pluck('name')->all())->toBe(['public'])
        ->and(manager()->forUser(null, ['secret.view'])->pluck('name')->all())->toBe(['public', 'secret']);
});

it('mantém o pai visível se tiver filhos visíveis', function () {
    config()->set('menu.permission_mode', 'string');

    // O pai exige uma permissão que o utilizador não tem, mas o filho é permitido.
    $parent = MenuItem::create(['name' => 'parent', 'label' => 'Pai', 'permission' => 'pai.only']);
    MenuItem::create(['name' => 'child', 'label' => 'Filho', 'parent_id' => $parent->id, 'permission' => 'filho.ok']);

    expect(nomes(manager()->forUser(null, ['filho.ok'])))->toBe('parent(child)');
});

it('um separador não mantém visível um grupo restrito', function () {
    config()->set('menu.permission_mode', 'string');

    $admin = MenuItem::create(['name' => 'admin', 'label' => 'Admin', 'permission' => 'admin.access']);
    MenuItem::create(['name' => 'users', 'label' => 'U', 'parent_id' => $admin->id, 'permission' => 'users.view']);
    MenuItem::create(['name' => 'sep', 'label' => '—', 'parent_id' => $admin->id, 'is_separator' => true]);
    MenuItem::create(['name' => 'home', 'label' => 'Início']);

    expect(nomes(manager()->forUser(null, [])))->toBe('home');
});

it('remove separadores soltos no início, no fim e repetidos', function () {
    config()->set('menu.permission_mode', 'string');

    MenuItem::create(['name' => 'sep1', 'label' => '—', 'is_separator' => true, 'order' => 1]);
    MenuItem::create(['name' => 'a', 'label' => 'A', 'order' => 2]);
    MenuItem::create(['name' => 'sep2', 'label' => '—', 'is_separator' => true, 'order' => 3]);
    MenuItem::create(['name' => 'oculto', 'label' => 'X', 'permission' => 'x', 'order' => 4]);
    MenuItem::create(['name' => 'sep3', 'label' => '—', 'is_separator' => true, 'order' => 5]);
    MenuItem::create(['name' => 'b', 'label' => 'B', 'order' => 6]);
    MenuItem::create(['name' => 'sep4', 'label' => '—', 'is_separator' => true, 'order' => 7]);

    expect(nomes(manager()->forUser(null, [])))->toBe('a,sep2,b');
});

it('esconde um grupo sem link cujos filhos ficaram todos escondidos', function () {
    config()->set('menu.permission_mode', 'string');

    $grupo = MenuItem::create(['name' => 'relatorios', 'label' => 'Relatórios']);
    MenuItem::create(['name' => 'r1', 'label' => 'R1', 'parent_id' => $grupo->id, 'permission' => 'reports.view']);
    MenuItem::create(['name' => 'home', 'label' => 'Início']);

    expect(nomes(manager()->forUser(null, [])))->toBe('home')
        ->and(nomes(manager()->forUser(null, ['reports.view'])))->toBe('relatorios(r1),home');
});

it('mantém um item com link próprio mesmo que os filhos fiquem escondidos', function () {
    config()->set('menu.permission_mode', 'string');

    $pai = MenuItem::create(['name' => 'vendas', 'label' => 'Vendas', 'route' => '/vendas']);
    MenuItem::create(['name' => 'v1', 'label' => 'V1', 'parent_id' => $pai->id, 'permission' => 'sales.admin']);

    expect(nomes(manager()->forUser(null, [])))->toBe('vendas');
});

it('no modo none devolve todos os itens, só sem separadores soltos', function () {
    MenuItem::create(['name' => 'a', 'label' => 'A', 'permission' => 'qualquer', 'order' => 1]);
    MenuItem::create(['name' => 'sep', 'label' => '—', 'is_separator' => true, 'order' => 2]);

    expect(nomes(manager()->forUser(null, [])))->toBe('a');
});

it('aceita permissões em Collection, arrays, objetos, models e enums', function () {
    config()->set('menu.permission_mode', 'string');

    foreach (['p1', 'p2', 'p3', 'p4', 'users.view'] as $i => $perm) {
        MenuItem::create(['name' => $perm, 'label' => $perm, 'permission' => $perm, 'order' => $i]);
    }

    $permissoes = collect([
        'p1',
        ['name' => 'p2'],
        (object) ['name' => 'p3'],
        new MenuItem(['name' => 'p4']),
        Permissao::VerUtilizadores,
        null,
        ['sem-nome' => 'ignorado'],
    ]);

    expect(nomes(manager()->forUser(null, $permissoes)))->toBe('p1,p2,p3,p4,users.view');
});

it('no modo id compara ids, venham como número ou texto', function () {
    config()->set('menu.permission_mode', 'id');

    MenuItem::create(['name' => 'a', 'label' => 'A', 'permission' => '7']);
    MenuItem::create(['name' => 'b', 'label' => 'B', 'permission' => '8']);

    expect(nomes(manager()->forUser(null, [7])))->toBe('a')
        ->and(nomes(manager()->forUser(null, [['id' => 8]])))->toBe('b');
});

it('resolve label de permissão no modo id contra a tabela configurada', function () {
    // Uma tabela de permissões qualquer "do projeto".
    Schema::create('permissions', function ($table) {
        $table->id();
        $table->string('name');
    });

    $permId = DB::table('permissions')->insertGetId(['name' => 'gerir.tudo']);

    config()->set('menu.permission_mode', 'id');
    config()->set('menu.resolver', ['table' => 'permissions', 'key' => 'id', 'column' => 'name']);

    $item = MenuItem::create(['name' => 'a', 'label' => 'A', 'permission' => (string) $permId]);

    expect($item->resolvedPermissionLabel())->toBe('gerir.tudo')
        ->and($item->isVisibleTo([$permId]))->toBeTrue()
        ->and($item->isVisibleTo([999]))->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| Modo de permissão
|--------------------------------------------------------------------------
*/

it('rejeita um permission_mode inválido com uma mensagem clara', function () {
    config()->set('menu.permission_mode', 'strings');

    expect(fn () => manager()->forUser(null, []))
        ->toThrow(InvalidArgumentException::class, "Usa 'none', 'string' ou 'id'");
});

it('aceita o permission_mode com maiúsculas, espaços ou vazio', function () {
    config()->set('menu.permission_mode', ' String ');
    expect(manager()->permissionMode())->toBe('string');

    config()->set('menu.permission_mode', '');
    expect(manager()->permissionMode())->toBe('none');
});

/*
|--------------------------------------------------------------------------
| Permissões do utilizador e hasPermission()
|--------------------------------------------------------------------------
*/

it('usa o resolver definido com Menu::resolvePermissionsUsing()', function () {
    config()->set('menu.permission_mode', 'string');
    MenuItem::create(['name' => 'secret', 'label' => 'S', 'permission' => 'secret.view']);

    Menu::resolvePermissionsUsing(fn ($user) => collect(['secret.view']));

    expect(nomes(Menu::forUser()))->toBe('secret')
        ->and(Menu::resolveUserPermissions())->toBe(['secret.view']);

    Menu::resolvePermissionsUsing(null);

    expect(Menu::forUser())->toBeEmpty();
});

it('aceita na config uma classe invocável, "Classe@metodo" ou [Classe, metodo]', function () {
    config()->set('menu.user_permissions', MenuPermissions::class);
    expect(manager()->resolveUserPermissions())->toBe(['via.invoke']);

    config()->set('menu.user_permissions', MenuPermissions::class.'@resolve');
    expect(manager()->resolveUserPermissions())->toBe(['via.metodo']);

    config()->set('menu.user_permissions', [MenuPermissions::class, 'resolve']);
    expect(manager()->resolveUserPermissions())->toBe(['via.metodo']);
});

it('explica o erro quando config(menu.user_permissions) é inválida', function () {
    config()->set('menu.user_permissions', 'nao-existe');

    expect(fn () => manager()->resolveUserPermissions())
        ->toThrow(InvalidArgumentException::class, 'menu.user_permissions');
});

it('usa $user->getAllPermissions() quando não há resolver configurado', function () {
    config()->set('menu.permission_mode', 'string');

    $user = new class {
        public function getAllPermissions()
        {
            return collect([(object) ['name' => 'users.view']]);
        }
    };

    expect(manager()->resolveUserPermissions($user))->toBe(['users.view'])
        ->and(manager()->resolveUserPermissions(new stdClass()))->toBe([]);
});

it('hasPermission aceita uma ou várias permissões (basta ter uma)', function () {
    config()->set('menu.permission_mode', 'string');
    Menu::resolvePermissionsUsing(fn () => ['users.edit']);

    expect(Menu::hasPermission('users.edit'))->toBeTrue()
        ->and(Menu::hasPermission('users.view'))->toBeFalse()
        ->and(Menu::hasPermission(['users.view', 'users.edit']))->toBeTrue()
        ->and(Menu::hasPermission('users.view|users.edit'))->toBeTrue()
        ->and(Menu::hasPermission([]))->toBeTrue();
});

it('hasPermission devolve sempre true no modo none', function () {
    Menu::resolvePermissionsUsing(fn () => []);

    expect(Menu::hasPermission('qualquer.coisa'))->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| Cache
|--------------------------------------------------------------------------
*/

it('guarda a árvore em cache e invalida ao gravar', function () {
    MenuItem::create(['name' => 'a', 'label' => 'A']);

    manager()->tree();
    expect(Cache::store('array')->has('laravel_menu:tree'))->toBeTrue();

    // Um item novo limpa a cache sozinho (evento saved).
    MenuItem::create(['name' => 'b', 'label' => 'B']);
    expect(Cache::store('array')->has('laravel_menu:tree'))->toBeFalse();

    expect(manager()->tree())->toHaveCount(2);
});

it('invalida a cache ao apagar, restaurar e apagar definitivamente', function () {
    $item = MenuItem::create(['name' => 'a', 'label' => 'A']);

    foreach (['delete', 'restore', 'forceDelete'] as $acao) {
        manager()->tree();
        $item->{$acao}();

        expect(Cache::store('array')->has('laravel_menu:tree'))->toBeFalse("não limpou em {$acao}()");
    }
});

it('com ttl vazio, null ou 0 guarda a árvore sem prazo', function (mixed $ttl) {
    config()->set('menu.cache.ttl', $ttl);
    MenuItem::create(['name' => 'a', 'label' => 'A']);

    manager()->tree();

    expect(Cache::store('array')->has('laravel_menu:tree'))->toBeTrue();
})->with(['vazio' => '', 'null' => null, 'zero' => '0']);

it('com ttl em segundos guarda a árvore com prazo', function () {
    config()->set('menu.cache.ttl', '60');
    MenuItem::create(['name' => 'a', 'label' => 'A']);

    manager()->tree();
    expect(Cache::store('array')->has('laravel_menu:tree'))->toBeTrue();

    $this->travel(61)->seconds();
    expect(Cache::store('array')->has('laravel_menu:tree'))->toBeFalse();
});

it('não serve uma árvore antiga depois de desligar e voltar a ligar a cache', function () {
    MenuItem::create(['name' => 'a', 'label' => 'A']);
    manager()->tree();

    config()->set('menu.cache.enabled', false);
    MenuItem::create(['name' => 'b', 'label' => 'B']);
    config()->set('menu.cache.enabled', true);

    expect(manager()->tree())->toHaveCount(2);
});

it('com a cache desligada ignora erros de um store que nem está em uso', function () {
    config()->set('menu.cache.enabled', false);
    config()->set('menu.cache.store', 'store-que-nao-existe');

    MenuItem::create(['name' => 'a', 'label' => 'A']);

    expect(manager()->tree())->toHaveCount(1);
});

it('o comando artisan reconstrói a cache', function () {
    MenuItem::create(['name' => 'a', 'label' => 'A']);

    $this->artisan('laravel-menu:cache')
        ->expectsOutputToContain('Cache de menus reconstruída')
        ->assertSuccessful();

    expect(Cache::store('array')->has('laravel_menu:tree'))->toBeTrue();
});

it('o comando artisan --flush limpa a cache', function () {
    MenuItem::create(['name' => 'a', 'label' => 'A']);
    manager()->tree();

    $this->artisan('laravel-menu:cache', ['--flush' => true])
        ->expectsOutputToContain('limpa com sucesso')
        ->assertSuccessful();

    expect(Cache::store('array')->has('laravel_menu:tree'))->toBeFalse();
});
