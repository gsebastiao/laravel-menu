<?php

declare(strict_types=1);

use Gsebastiao\LaravelMenu\Facades\Menu;
use Gsebastiao\LaravelMenu\Models\MenuItem;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    Route::get('/utilizadores', fn () => 'ok')->name('users.index');
    Route::get('/utilizadores/criar', fn () => 'ok')->name('users.create');
    Route::get('/utilizadores/{user}/editar', fn () => 'ok')->name('users.edit');
    Route::get('/relatorios', fn () => 'ok')->name('reports');

    app('router')->getRoutes()->refreshNameLookups();
});

/**
 * Um pedido para $uri, já associado à rota correspondente (como no Laravel).
 */
function pedido(string $uri): Request
{
    $request = Request::create($uri);
    $route = app('router')->getRoutes()->match($request);
    $request->setRouteResolver(fn () => $route);

    return $request;
}

/*
|--------------------------------------------------------------------------
| Menu::url()
|--------------------------------------------------------------------------
*/

it('gera o link de um nome de rota, com params', function () {
    expect(Menu::url(['route' => 'users.index']))->toBe('http://localhost/utilizadores')
        ->and(Menu::url(['route' => 'users.edit', 'params' => ['user' => 5]]))->toBe('http://localhost/utilizadores/5/editar')
        ->and(Menu::url(['route' => 'users.index', 'params' => ['tab' => 'ativos']]))->toBe('http://localhost/utilizadores?tab=ativos');
});

it('devolve null para um nome de rota que não existe, em vez de rebentar', function () {
    expect(Menu::url(['route' => 'admin.users.index']))->toBeNull();
});

it('devolve null se faltarem parâmetros obrigatórios da rota', function () {
    expect(Menu::url(['route' => 'users.edit']))->toBeNull();
});

it('não enche o log quando faltam parâmetros obrigatórios da rota', function () {
    $reportadas = [];

    app()->instance(ExceptionHandler::class, new class($reportadas) implements ExceptionHandler
    {
        public function __construct(private array &$reportadas) {}

        public function report(Throwable $e): void
        {
            $this->reportadas[] = $e::class;
        }

        public function shouldReport(Throwable $e): bool
        {
            return true;
        }

        public function render($request, Throwable $e)
        {
            throw $e;
        }

        public function renderForConsole($output, Throwable $e): void {}
    });

    // Um item mal configurado é desenhado em cada página: se cada desenho
    // escrevesse uma exceção no log, o log ficava inutilizável.
    expect(Menu::url(['route' => 'users.edit']))->toBeNull()
        ->and($reportadas)->toBe([]);
});

it('aceita caminhos, endereços completos e âncoras', function () {
    expect(Menu::url(['route' => '/sobre']))->toBe('http://localhost/sobre')
        ->and(Menu::url(['route' => 'ajuda/faq']))->toBe('http://localhost/ajuda/faq')
        ->and(Menu::url(['route' => 'https://github.com/gsebastiao']))->toBe('https://github.com/gsebastiao')
        ->and(Menu::url(['route' => 'mailto:geral@exemplo.co.mz']))->toBe('mailto:geral@exemplo.co.mz')
        ->and(Menu::url(['route' => '#contactos']))->toBe('#contactos');
});

it('não gera links perigosos nem links para grupos e separadores', function () {
    expect(Menu::url(['route' => 'javascript:alert(1)']))->toBeNull()
        ->and(Menu::url(['route' => ' JavaScript:alert(1)']))->toBeNull()
        ->and(Menu::url(['route' => null]))->toBeNull()
        ->and(Menu::url(['route' => '']))->toBeNull()
        ->and(Menu::url(['route' => '/sobre', 'is_separator' => true]))->toBeNull();
});

it('aceita também um MenuItem em vez de um array', function () {
    $item = MenuItem::create(['name' => 'u', 'label' => 'U', 'route' => 'users.edit', 'params' => ['user' => 9]]);

    expect(Menu::url($item))->toBe('http://localhost/utilizadores/9/editar');
});

/*
|--------------------------------------------------------------------------
| Menu::isActive()
|--------------------------------------------------------------------------
*/

it('marca como ativo o item da rota atual', function () {
    expect(Menu::isActive(['route' => 'reports'], pedido('/relatorios')))->toBeTrue()
        ->and(Menu::isActive(['route' => 'reports'], pedido('/utilizadores')))->toBeFalse();
});

it('um item .index fica ativo nas outras páginas do mesmo recurso', function () {
    expect(Menu::isActive(['route' => 'users.index'], pedido('/utilizadores/criar')))->toBeTrue()
        ->and(Menu::isActive(['route' => 'users.index'], pedido('/utilizadores/3/editar')))->toBeTrue()
        ->and(Menu::isActive(['route' => 'users.create'], pedido('/utilizadores')))->toBeFalse();
});

it('compara caminhos e endereços completos', function () {
    expect(Menu::isActive(['route' => '/sobre'], Request::create('/sobre')))->toBeTrue()
        ->and(Menu::isActive(['route' => '/'], Request::create('/')))->toBeTrue()
        ->and(Menu::isActive(['route' => '/sobre'], Request::create('/contactos')))->toBeFalse()
        ->and(Menu::isActive(['route' => 'http://localhost/sobre?x=1'], Request::create('/sobre')))->toBeTrue();
});

it('um grupo fica ativo quando um descendente está ativo', function () {
    $grupo = [
        'route' => null,
        'children' => [
            ['route' => '/sobre', 'children' => []],
            ['route' => null, 'children' => [
                ['route' => 'reports', 'children' => []],
            ]],
        ],
    ];

    expect(Menu::isActive($grupo, pedido('/relatorios')))->toBeTrue()
        ->and(Menu::isActive($grupo, pedido('/utilizadores')))->toBeFalse();
});

it('usa o pedido atual quando nenhum é passado', function () {
    Route::get('/verificar', fn () => Menu::isActive(['route' => 'verificar']) ? 'ativo' : 'inativo')->name('verificar');
    app('router')->getRoutes()->refreshNameLookups();

    $this->get('/verificar')->assertContent('ativo');
});
