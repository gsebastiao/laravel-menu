<?php

declare(strict_types=1);

namespace Gsebastiao\LaravelMenu\Services;

use Gsebastiao\LaravelMenu\Models\MenuItem;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class MenuManager
{
    /**
     * Devolve a árvore completa de menus (todos os itens ativos), lida da
     * cache quando possível. Não aplica filtro de permissões.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function tree(): Collection
    {
        if (! $this->cacheEnabled()) {
            return $this->buildTree();
        }

        $cached = $this->cache()->get($this->cacheKey());

        if ($cached !== null) {
            return collect($cached);
        }

        $tree = $this->buildTree();

        $this->put($tree);

        return $tree;
    }

    /**
     * Devolve a árvore filtrada pelas permissões fornecidas (ou pelas do
     * utilizador autenticado, se não forem passadas).
     *
     * @param  array<int|string>|null  $userPermissions
     * @return Collection<int, array<string, mixed>>
     */
    public function forUser(mixed $user = null, ?array $userPermissions = null): Collection
    {
        $permissions = $userPermissions ?? $this->resolveUserPermissions($user);

        return $this->filterTree($this->tree(), $permissions);
    }

    /**
     * Monta a árvore a partir da base de dados, num único SELECT (sem joins),
     * e transforma numa estrutura aninhada em arrays puros (cacheáveis).
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function buildTree(): Collection
    {
        /** @var class-string<MenuItem> $model */
        $model = config('menu.model', MenuItem::class);

        $items = $model::query()
            ->active()
            ->ordered()
            ->get();

        return $this->nest($items);
    }

    /**
     * Transforma uma coleção plana em árvore aninhada (arrays).
     *
     * @param  \Illuminate\Support\Collection<int, MenuItem>  $items
     * @return Collection<int, array<string, mixed>>
     */
    protected function nest(Collection $items, ?int $parentId = null): Collection
    {
        return $items
            ->where('parent_id', $parentId)
            ->values()
            ->map(function (MenuItem $item) use ($items) {
                $node = $item->attributesToArray();
                $node['children'] = $this->nest($items, $item->id)->all();

                return $node;
            });
    }

    /**
     * Filtra recursivamente a árvore pelas permissões do utilizador.
     * Um nó-pai é mantido se ele próprio for visível OU se tiver filhos
     * visíveis (evita "buracos" na navegação).
     *
     * @param  Collection<int, array<string, mixed>>  $tree
     * @param  array<int|string>  $permissions
     * @return Collection<int, array<string, mixed>>
     */
    protected function filterTree(Collection $tree, array $permissions): Collection
    {
        $mode = config('menu.permission_mode', 'none');

        return $tree
            ->map(function (array $node) use ($permissions, $mode) {
                $children = $this->filterTree(
                    collect($node['children'] ?? []),
                    $permissions
                );

                $node['children'] = $children->all();

                $selfVisible = $this->nodeVisible($node, $permissions, $mode);

                if ($selfVisible || $children->isNotEmpty()) {
                    return $node;
                }

                return null;
            })
            ->filter()
            ->values();
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  array<int|string>  $permissions
     */
    protected function nodeVisible(array $node, array $permissions, string $mode): bool
    {
        $permission = $node['permission'] ?? null;

        if ($mode === 'none' || $permission === null || $permission === '') {
            return true;
        }

        $needle = (string) $permission;
        $haystack = array_map(static fn($p) => (string) $p, $permissions);

        return in_array($needle, $haystack, true);
    }

    /*
    |--------------------------------------------------------------------------
    | Permissões do utilizador
    |--------------------------------------------------------------------------
    */

    /**
     * @return array<int|string>
     */
    public function resolveUserPermissions(mixed $user = null): array
    {
        $user ??= auth()->user();

        $callback = config('menu.user_permissions');

        if (is_callable($callback)) {
            $result = $callback($user);

            return $this->normalizePermissions($result);
        }

        // Default agnóstico: tenta getAllPermissions() se existir
        // (compatível com vários setups, incluindo Spatie), senão vazio.
        if ($user !== null && method_exists($user, 'getAllPermissions')) {
            return $this->normalizePermissions($user->getAllPermissions());
        }

        return [];
    }

    /**
     * @return array<int|string>
     */
    protected function normalizePermissions(mixed $permissions): array
    {
        if ($permissions instanceof Collection) {
            $permissions = $permissions->all();
        }

        if (! is_array($permissions)) {
            return [];
        }

        $mode = config('menu.permission_mode', 'none');
        $resolver = config('menu.resolver');

        return array_values(array_map(function ($p) use ($mode, $resolver) {
            // Objetos (ex.: models de permissão) -> extrai a coluna relevante.
            if (is_object($p)) {
                if ($mode === 'id') {
                    return $p->{$resolver['key']} ?? $p->id ?? null;
                }

                return $p->{$resolver['column']} ?? $p->name ?? (string) $p;
            }

            return $p;
        }, $permissions));
    }

    /*
    |--------------------------------------------------------------------------
    | Cache
    |--------------------------------------------------------------------------
    */

    public function flushCache(): void
    {
        if ($this->cacheEnabled()) {
            $this->cache()->forget($this->cacheKey());
        }
    }

    public function rebuildCache(): Collection
    {
        $this->flushCache();

        $tree = $this->buildTree();

        if ($this->cacheEnabled()) {
            $this->put($tree);
        }

        return $tree;
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $tree
     */
    protected function put(Collection $tree): void
    {
        $ttl = config('menu.cache.ttl');

        if ($ttl === null) {
            $this->cache()->forever($this->cacheKey(), $tree->all());

            return;
        }

        $this->cache()->put($this->cacheKey(), $tree->all(), (int) $ttl);
    }

    protected function cache(): CacheRepository
    {
        $store = config('menu.cache.store');

        return $store ? Cache::store($store) : Cache::store();
    }

    protected function cacheEnabled(): bool
    {
        return (bool) config('menu.cache.enabled', true);
    }

    protected function cacheKey(): string
    {
        return config('menu.cache.key', 'laravel_menu') . ':tree';
    }
}
