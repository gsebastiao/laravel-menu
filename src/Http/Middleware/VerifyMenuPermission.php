<?php

declare(strict_types=1);

namespace Gsebastiao\DynamicMenu\Http\Middleware;

use Closure;
use Gsebastiao\DynamicMenu\Services\MenuManager;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Verifica se o utilizador possui uma permissão antes de deixar prosseguir.
 *
 * Uso em rotas:
 *   Route::get('/admin', ...)->middleware('menu.permission:user.create');
 *
 * No modo 'none' o middleware é um no-op (deixa passar sempre), o que permite
 * usar o mesmo código em projetos simples sem permissões.
 */
class VerifyMenuPermission
{
    public function __construct(protected MenuManager $manager)
    {
    }

    public function handle(Request $request, Closure $next, ?string $permission = null): Response
    {
        $mode = config('dynamic-menu.permission_mode', 'none');

        // Sem permissões ou sem exigência específica: passa.
        if ($mode === 'none' || $permission === null || $permission === '') {
            return $next($request);
        }

        $userPermissions = array_map(
            static fn ($p) => (string) $p,
            $this->manager->resolveUserPermissions($request->user())
        );

        if (! in_array((string) $permission, $userPermissions, true)) {
            abort(Response::HTTP_FORBIDDEN, 'Sem permissão para aceder a este recurso.');
        }

        return $next($request);
    }
}
