<?php

declare(strict_types=1);

namespace Gsebastiao\LaravelMenu\Http\Middleware;

use Gsebastiao\LaravelMenu\Services\MenuManager;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Http\Request;
use Closure;

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
    public function __construct(protected MenuManager $manager) {}

    public function handle(Request $request, Closure $next, ?string $permission = null): Response
    {
        $mode = config('menu.permission_mode', 'none');

        // Sem permissões ou sem exigência específica: passa.
        if ($mode === 'none' || $permission === null || $permission === '') {
            return $next($request);
        }

        $userPermissions = array_map(
            static fn($p) => (string) $p,
            $this->manager->resolveUserPermissions($request->user())
        );

        if (! in_array((string) $permission, $userPermissions, true)) {
            abort(Response::HTTP_FORBIDDEN, 'Sem permissão para aceder a este recurso.');
        }

        return $next($request);
    }
}
