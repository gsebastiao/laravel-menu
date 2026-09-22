<?php

declare(strict_types=1);

use Composer\InstalledVersions;
use Gsebastiao\LaravelMenu\Facades\Menu;
use Gsebastiao\LaravelMenu\MenuServiceProvider;
use Gsebastiao\LaravelMenu\Models\MenuItem;
use Gsebastiao\LaravelMenu\Support\AuthzSupport;
use Gsebastiao\LaravelMenu\Tests\Fixtures\UtilizadorAuthz;
use Gsebastiao\LaravelMenu\Tests\Fixtures\UtilizadorSemTrait;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

$semPacote = ! AuthzSupport::packageInstalled();

/**
 * Cria a tabela `users` e as tabelas do gsebastiao/laravel-authz a partir das
 * migrations do pacote instalado (em vez de as escrever aqui à mão, que
 * divergiriam à primeira mudança de esquema).
 */
function criarTabelasDoAuthz(): void
{
    Schema::create('users', function (Blueprint $table) {
        $table->id();
        $table->string('name');
    });

    $ficheiros = glob(InstalledVersions::getInstallPath(AuthzSupport::PACKAGE_NAME).'/database/migrations/*.php') ?: [];

    expect($ficheiros)->toHaveCount(2);

    // `require` (e não `require_once`) porque isto corre uma vez por teste.
    foreach ($ficheiros as $ficheiro) {
        (require $ficheiro)->up();
    }
}

function servicoAuthz(): object
{
    return app(AuthzSupport::SERVICE);
}

/** Cria uma permissão no catálogo do laravel-authz e devolve o id dela. */
function criarPermissao(string $nome, ?string $etiqueta = null): int
{
    [$modulo, $acao] = array_pad(explode('.', $nome, 2), 2, 'ver');

    return servicoAuthz()->createPermission($nome, $modulo, $acao, $etiqueta ?? 'Etiqueta de '.$nome);
}

/** @return list<string> */
function nomesVisiveis(mixed $user): array
{
    return collect(Menu::forUser($user))->pluck('name')->all();
}

/*
|--------------------------------------------------------------------------
| Sem o pacote gsebastiao/laravel-authz instalado
|--------------------------------------------------------------------------
*/

it('não mexe em quem não usa nenhum pacote de permissões', function () {
    config()->set('menu.permission_mode', 'string');
    MenuItem::create(['name' => 'users', 'label' => 'Utilizadores', 'permission' => 'users.view']);
    MenuItem::create(['name' => 'inicio', 'label' => 'Início']);

    expect(config('menu.user_permissions'))->toBeNull()
        ->and(Menu::resolveUserPermissions(new stdClass()))->toBe([])
        ->and(nomesVisiveis(new stdClass()))->toBe(['inicio']);
});

it('explica no arranque que MENU_USER_PERMISSION=authz precisa do pacote', function () {
    config()->set('menu.user_permissions', 'authz');

    expect(fn () => (new MenuServiceProvider(app()))->boot(app('router')))
        ->toThrow(RuntimeException::class, 'composer require gsebastiao/laravel-authz');
})->skip(! $semPacote, 'Cenário para quando gsebastiao/laravel-authz NÃO está instalado.');

/*
|--------------------------------------------------------------------------
| Com o pacote gsebastiao/laravel-authz instalado
|--------------------------------------------------------------------------
*/

describe('com gsebastiao/laravel-authz instalado', function () use ($semPacote) {
    beforeEach(function () use ($semPacote) {
        if (! $semPacote) {
            criarTabelasDoAuthz();
            config()->set('menu.permission_mode', 'string');
        }
    });

    it('descobre sozinho as permissões do utilizador, sem configuração nenhuma', function () {
        MenuItem::create(['name' => 'users', 'label' => 'Utilizadores', 'permission' => 'users.view']);
        MenuItem::create(['name' => 'faturas', 'label' => 'Faturas', 'permission' => 'faturas.ver']);
        MenuItem::create(['name' => 'inicio', 'label' => 'Início']);

        $user = UtilizadorAuthz::create(['name' => 'Ana']);
        $user->grantPermission(criarPermissao('users.view'));
        criarPermissao('faturas.ver');

        expect(config('menu.user_permissions'))->toBeNull()
            ->and(Menu::resolveUserPermissions($user))->toBe(['users.view'])
            ->and(nomesVisiveis($user))->toBe(['users', 'inicio']);
    })->skip($semPacote, 'Requer gsebastiao/laravel-authz.');

    it('aceita a fonte escrita na config, com maiúsculas ou espaços', function () {
        config()->set('menu.user_permissions', ' Authz ');

        MenuItem::create(['name' => 'users', 'label' => 'Utilizadores', 'permission' => 'users.view']);

        $user = UtilizadorAuthz::create(['name' => 'Ana']);
        $user->grantPermission(criarPermissao('users.view'));

        expect(nomesVisiveis($user))->toBe(['users']);
    })->skip($semPacote, 'Requer gsebastiao/laravel-authz.');

    it('no modo id lê os ids das permissões, e não os nomes', function () {
        config()->set('menu.permission_mode', 'id');

        $permitida = criarPermissao('users.view');
        $negada = criarPermissao('faturas.ver');

        MenuItem::create(['name' => 'users', 'label' => 'Utilizadores', 'permission' => (string) $permitida]);
        MenuItem::create(['name' => 'faturas', 'label' => 'Faturas', 'permission' => (string) $negada]);

        $user = UtilizadorAuthz::create(['name' => 'Ana']);
        $user->grantPermission($permitida);

        expect(Menu::resolveUserPermissions($user))->toBe([$permitida])
            ->and(nomesVisiveis($user))->toBe(['users']);
    })->skip($semPacote, 'Requer gsebastiao/laravel-authz.');

    it('mostra o que vem de um grupo e esconde o que a negação individual tira', function () {
        MenuItem::create(['name' => 'users', 'label' => 'Utilizadores', 'permission' => 'users.view']);

        $permissao = criarPermissao('users.view');
        $grupo = servicoAuthz()->createGroup('Gestores');
        servicoAuthz()->grantPermissionToGroup($grupo, $permissao);

        $user = UtilizadorAuthz::create(['name' => 'Ana']);
        $user->assignRole('Gestores');

        expect(nomesVisiveis($user))->toBe(['users']);

        // A negação individual vence o grupo (cascata do laravel-authz).
        $user->denyPermission($permissao);

        expect(nomesVisiveis($user))->toBe([]);
    })->skip($semPacote, 'Requer gsebastiao/laravel-authz.');

    it('lê as permissões pelo id do utilizador quando falta o trait no model', function () {
        MenuItem::create(['name' => 'users', 'label' => 'Utilizadores', 'permission' => 'users.view']);

        $user = UtilizadorSemTrait::create(['name' => 'Rui']);
        servicoAuthz()->grantPermissionToUser($user->id, criarPermissao('users.view'), ['is_granted' => true]);

        expect(Menu::resolveUserPermissions($user))->toBe(['users.view'])
            ->and(nomesVisiveis($user))->toBe(['users']);
    })->skip($semPacote, 'Requer gsebastiao/laravel-authz.');

    it('um visitante não vê os itens com permissão', function () {
        MenuItem::create(['name' => 'users', 'label' => 'Utilizadores', 'permission' => 'users.view']);
        MenuItem::create(['name' => 'inicio', 'label' => 'Início']);

        expect(Menu::resolveUserPermissions(null))->toBe([])
            ->and(collect(Menu::forUser())->pluck('name')->all())->toBe(['inicio']);
    })->skip($semPacote, 'Requer gsebastiao/laravel-authz.');

    it('não rouba a vez ao Spatie: um model com getAllPermissions() continua a mandar', function () {
        $user = new class
        {
            public function getAllPermissions()
            {
                return collect([(object) ['name' => 'vem.do.spatie']]);
            }
        };

        expect(Menu::resolveUserPermissions($user))->toBe(['vem.do.spatie']);
    })->skip($semPacote, 'Requer gsebastiao/laravel-authz.');

    it('o resolver definido em código continua a ter a última palavra', function () {
        $user = UtilizadorAuthz::create(['name' => 'Ana']);
        $user->grantPermission(criarPermissao('users.view'));

        Menu::resolvePermissionsUsing(fn () => ['escrita.a.mao']);

        expect(Menu::resolveUserPermissions($user))->toBe(['escrita.a.mao']);

        Menu::resolvePermissionsUsing(null);

        expect(Menu::resolveUserPermissions($user))->toBe(['users.view']);
    })->skip($semPacote, 'Requer gsebastiao/laravel-authz.');

    it('o middleware menu.permission usa as permissões do laravel-authz', function () {
        Route::middleware(['menu.permission:users.view'])->get('/utilizadores', fn () => 'ok');

        $user = UtilizadorAuthz::create(['name' => 'Ana']);
        criarPermissao('users.view');

        $this->actingAs($user)->get('/utilizadores')->assertForbidden();

        $user->grantPermission('users.view');

        $this->actingAs($user)->get('/utilizadores')->assertOk();
    })->skip($semPacote, 'Requer gsebastiao/laravel-authz.');

    it('resolvedPermissionLabel lê o catálogo de permissões do laravel-authz', function () {
        config()->set('menu.permission_mode', 'id');
        config()->set('menu.resolver.table', 'auth_permissions');
        config()->set('menu.resolver.column', 'permission');

        $id = criarPermissao('users.view', 'Ver utilizadores');
        $item = MenuItem::create(['name' => 'users', 'label' => 'Utilizadores', 'permission' => (string) $id]);

        expect($item->resolvedPermissionLabel())->toBe('users.view');

        // A coluna `label` do laravel-authz traz o nome amigável.
        config()->set('menu.resolver.column', 'label');

        expect($item->resolvedPermissionLabel())->toBe('Ver utilizadores');
    })->skip($semPacote, 'Requer gsebastiao/laravel-authz.');
});
