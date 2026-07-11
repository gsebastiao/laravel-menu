<?php

declare(strict_types=1);

use Gsebastiao\DynamicMenu\Http\Middleware\VerifyMenuPermission;
use Gsebastiao\DynamicMenu\Services\MenuManager;
use Illuminate\Http\Request;

function runMiddleware(?string $permission, array $userPermissions): int
{
    // Injeta um manager que devolve permissões fixas, sem depender de auth.
    $manager = new class($userPermissions) extends MenuManager {
        public function __construct(private array $perms) {}
        public function resolveUserPermissions(mixed $user = null): array
        {
            return $this->perms;
        }
    };

    $middleware = new VerifyMenuPermission($manager);

    $response = $middleware->handle(
        Request::create('/x', 'GET'),
        fn () => new \Illuminate\Http\Response('ok', 200),
        $permission
    );

    return $response->getStatusCode();
}

it('no modo none deixa passar mesmo exigindo permissão', function () {
    config()->set('dynamic-menu.permission_mode', 'none');

    expect(runMiddleware('admin.access', []))->toBe(200);
});

it('no modo string bloqueia sem permissão', function () {
    config()->set('dynamic-menu.permission_mode', 'string');

    expect(fn () => runMiddleware('admin.access', ['outra']))
        ->toThrow(\Symfony\Component\HttpKernel\Exception\HttpException::class);
});

it('no modo string deixa passar com permissão', function () {
    config()->set('dynamic-menu.permission_mode', 'string');

    expect(runMiddleware('admin.access', ['admin.access']))->toBe(200);
});
