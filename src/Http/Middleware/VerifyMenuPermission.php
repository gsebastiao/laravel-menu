<?php

declare(strict_types=1);

namespace Gsebastiao\LaravelMenu\Http\Middleware;

use Closure;
use Gsebastiao\LaravelMenu\Services\MenuManager;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Só deixa passar quem tiver a permissão indicada (ou uma delas).
 *
 *   Route::get('/utilizadores', ...)->middleware('menu.permission:users.view');
 *
 *   // Várias permissões: basta ter UMA delas.
 *   ->middleware('menu.permission:users.view,users.edit');
 *   ->middleware('menu.permission:users.view|users.edit');
 *
 * No modo 'none' deixa passar sempre, por isso o mesmo código serve para
 * projetos com e sem permissões. Quem não tiver permissão recebe um 403.
 */
class VerifyMenuPermission
{
    public function __construct(protected MenuManager $manager) {}

    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        if (! $this->manager->hasPermission($permissions, $request->user())) {
            abort(Response::HTTP_FORBIDDEN, 'Sem permissão para aceder a este recurso.');
        }

        return $next($request);
    }
}
