<?php

declare(strict_types=1);

use Gsebastiao\LaravelMenu\Facades\Menu;
use Gsebastiao\LaravelMenu\Http\Middleware\VerifyMenuPermission;
use Gsebastiao\LaravelMenu\Services\MenuManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Corre o middleware com permissões fixas, sem depender de autenticação.
 */
function runMiddleware(array $userPermissions, string ...$required): int
{
    $manager = new class($userPermissions) extends MenuManager {
        public function __construct(private array $perms) {}

        public function resolveUserPermissions(mixed $user = null): array
        {
            return $this->perms;
        }
    };

    $response = (new VerifyMenuPermission($manager))->handle(
        Request::create('/x', 'GET'),
        fn () => new \Illuminate\Http\Response('ok', 200),
        ...$required
    );

    return $response->getStatusCode();
}

it('no modo none deixa passar mesmo exigindo permissão', function () {
    config()->set('menu.permission_mode', 'none');

    expect(runMiddleware([], 'admin.access'))->toBe(200);
});

it('no modo string bloqueia sem permissão', function () {
    config()->set('menu.permission_mode', 'string');

    expect(fn () => runMiddleware(['outra'], 'admin.access'))->toThrow(HttpException::class);
});

it('no modo string deixa passar com permissão', function () {
    config()->set('menu.permission_mode', 'string');

    expect(runMiddleware(['admin.access'], 'admin.access'))->toBe(200);
});

it('com várias permissões basta ter uma (separadas por vírgula ou |)', function () {
    config()->set('menu.permission_mode', 'string');

    expect(runMiddleware(['users.edit'], 'users.view', 'users.edit'))->toBe(200)
        ->and(runMiddleware(['users.edit'], 'users.view|users.edit'))->toBe(200)
        ->and(fn () => runMiddleware(['outra'], 'users.view', 'users.edit'))->toThrow(HttpException::class);
});

it('deixa passar quando nenhuma permissão é indicada', function () {
    config()->set('menu.permission_mode', 'string');

    expect(runMiddleware([]))->toBe(200);
});

it('funciona numa rota real através do alias menu.permission', function () {
    config()->set('menu.permission_mode', 'string');

    Route::get('/admin', fn () => 'ok')->middleware('menu.permission:admin.access,users.view');

    Menu::resolvePermissionsUsing(fn () => ['users.view']);
    $this->get('/admin')->assertOk();

    Menu::resolvePermissionsUsing(fn () => []);
    $this->get('/admin')->assertForbidden();
});
