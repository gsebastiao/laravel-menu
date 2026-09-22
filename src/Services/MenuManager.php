<?php

declare(strict_types=1);

namespace Gsebastiao\LaravelMenu\Services;

use BackedEnum;
use Gsebastiao\LaravelMenu\Models\MenuItem;
use Gsebastiao\LaravelMenu\Support\AuthzSupport;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use InvalidArgumentException;
use Stringable;
use Throwable;
use UnitEnum;

class MenuManager
{
    /** Valores aceites em config('menu.permission_mode'). */
    public const MODES = ['none', 'string', 'id'];

    /**
     * Fontes de permissões com nome, aceites em config('menu.user_permissions')
     * (MENU_USER_PERMISSION) em vez de uma classe:
     *
     *   'auto'   -> descobre sozinho (é o que acontece sem configuração):
     *               laravel-authz, depois getAllPermissions() (Spatie).
     *   'authz'  -> gsebastiao/laravel-authz.
     *   'spatie' -> spatie/laravel-permission e compatíveis.
     */
    public const SOURCES = ['auto', 'authz', 'spatie'];

    /** Esquemas de URL que nunca viram link (evita `javascript:` num item). */
    protected const BLOCKED_SCHEMES = ['javascript', 'data', 'vbscript', 'file'];

    /**
     * Resolver registado com resolvePermissionsUsing().
     *
     * @var (callable(mixed): mixed)|null
     */
    protected $permissionsResolver = null;

    /*
    |--------------------------------------------------------------------------
    | Árvore
    |--------------------------------------------------------------------------
    */

    /**
     * A árvore completa (todos os itens ativos, aninhados), lida da cache
     * quando possível. Não aplica permissões.
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
     * A árvore pronta a mostrar a um utilizador: sem os itens que ele não pode
     * ver, sem grupos que ficaram vazios e sem separadores soltos.
     *
     * Sem argumentos, usa o utilizador autenticado.
     *
     * @param  iterable<mixed>|null  $userPermissions  Permissões a usar em vez das do utilizador.
     * @return Collection<int, array<string, mixed>>
     */
    public function forUser(mixed $user = null, ?iterable $userPermissions = null): Collection
    {
        $permissions = $userPermissions !== null
            ? $this->normalizePermissions($userPermissions)
            : $this->resolveUserPermissions($user);

        return $this->filterTree($this->tree(), $this->permissionMode(), $this->lookup($permissions));
    }

    /**
     * Monta a árvore a partir da base de dados, num único SELECT (sem joins),
     * em arrays simples (que podem ir para a cache).
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function buildTree(): Collection
    {
        /** @var class-string<MenuItem> $model */
        $model = config('menu.model', MenuItem::class);

        $byParent = $model::query()
            ->active()
            ->ordered()
            ->get()
            ->groupBy(static fn (MenuItem $item) => $item->parent_id ?? 0);

        return collect($this->nest($byParent, 0));
    }

    /**
     * Monta os nós de um nível. Só percorre a partir das raízes, por isso
     * os filhos de um item inativo (ou apagado) ficam de fora com ele.
     *
     * @param  Collection<int|string, Collection<int, MenuItem>>  $byParent
     * @return list<array<string, mixed>>
     */
    protected function nest(Collection $byParent, int $parentId): array
    {
        $nodes = [];

        foreach ($byParent->get($parentId, []) as $item) {
            $node = $item->attributesToArray();
            $node['children'] = $this->nest($byParent, (int) $item->getKey());
            $nodes[] = $node;
        }

        return $nodes;
    }

    /**
     * Regras de visibilidade:
     *  - um item aparece se o utilizador tiver a permissão dele (ou se não
     *    tiver permissão definida);
     *  - um pai sem permissão aparece na mesma se tiver filhos visíveis
     *    (para não deixar "buracos" na navegação);
     *  - um grupo (item sem link) cujos filhos ficaram todos escondidos
     *    desaparece, em vez de aparecer vazio;
     *  - separadores não contam como filhos visíveis e os que ficam soltos
     *    (no início, no fim ou repetidos) são removidos.
     *
     * @param  Collection<int, array<string, mixed>>  $nodes
     * @param  array<string, true>  $allowed
     * @return Collection<int, array<string, mixed>>
     */
    protected function filterTree(Collection $nodes, string $mode, array $allowed): Collection
    {
        $kept = [];

        foreach ($nodes as $node) {
            $hadChildren = ! empty($node['children']);
            $node['children'] = $this->filterTree(collect($node['children'] ?? []), $mode, $allowed)->all();

            if ($node['is_separator'] ?? false) {
                if ($this->nodeAllowed($node, $mode, $allowed)) {
                    $kept[] = $node;
                }

                continue;
            }

            if ($node['children'] !== []) {
                $kept[] = $node;

                continue;
            }

            if (! $this->nodeAllowed($node, $mode, $allowed)) {
                continue;
            }

            // Grupo sem link próprio que perdeu todos os filhos.
            if ($hadChildren && blank($node['route'] ?? null)) {
                continue;
            }

            $kept[] = $node;
        }

        return collect($this->withoutStraySeparators($kept));
    }

    /**
     * @param  list<array<string, mixed>>  $nodes
     * @return list<array<string, mixed>>
     */
    protected function withoutStraySeparators(array $nodes): array
    {
        $result = [];

        foreach ($nodes as $node) {
            $isSeparator = (bool) ($node['is_separator'] ?? false);
            $previousIsSeparator = $result !== [] && ($result[array_key_last($result)]['is_separator'] ?? false);

            if ($isSeparator && ($result === [] || $previousIsSeparator)) {
                continue;
            }

            $result[] = $node;
        }

        while ($result !== [] && ($result[array_key_last($result)]['is_separator'] ?? false)) {
            array_pop($result);
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  array<string, true>  $allowed
     */
    protected function nodeAllowed(array $node, string $mode, array $allowed): bool
    {
        $permission = $node['permission'] ?? null;

        if ($mode === 'none' || $permission === null || $permission === '') {
            return true;
        }

        return isset($allowed[(string) $permission]);
    }

    /*
    |--------------------------------------------------------------------------
    | Links e item ativo (para desenhar o menu)
    |--------------------------------------------------------------------------
    */

    /**
     * O endereço de um item, pronto para um href. Devolve null se o item não
     * tiver link (grupos, separadores) ou se o link não puder ser gerado.
     *
     * O campo `route` pode ter:
     *  - o nome de uma rota do Laravel: 'users.index' (usa `params` como parâmetros);
     *  - um caminho da aplicação: '/sobre';
     *  - um endereço completo: 'https://exemplo.com', 'mailto:...', 'tel:...';
     *  - uma âncora: '#contactos'.
     *
     * Um nome de rota que não existe devolve null, em vez de rebentar a página.
     *
     * @param  array<string, mixed>|MenuItem  $item
     */
    public function url(array|MenuItem $item): ?string
    {
        $target = $this->targetOf($item);

        if ($target === null) {
            return null;
        }

        if (Route::has($target)) {
            $params = data_get($item, 'params');

            try {
                return route($target, is_array($params) ? $params : []);
            } catch (Throwable) {
                // Falta um parâmetro obrigatório da rota: o item fica sem link.
                // Não é reportado ao log: senão um único item mal configurado
                // escrevia uma exceção em cada pedido.
                return null;
            }
        }

        if (str_starts_with($target, '#')) {
            return $target;
        }

        if (preg_match('/^([a-z][a-z0-9+.\-]*):/i', $target, $match) === 1) {
            return in_array(strtolower($match[1]), self::BLOCKED_SCHEMES, true) ? null : $target;
        }

        if (str_contains($target, '/')) {
            return url($target);
        }

        // Sem '/', sem esquema e sem rota registada: é um nome de rota que
        // não existe (ex.: um item de exemplo do seeder).
        return null;
    }

    /**
     * O item corresponde à página atual, ou tem algum descendente que
     * corresponde? Útil para destacar o item ativo e abrir o submenu certo.
     *
     * Um item com a rota 'users.index' também fica ativo em 'users.create',
     * 'users.edit', etc.
     *
     * @param  array<string, mixed>|MenuItem  $item
     */
    public function isActive(array|MenuItem $item, ?Request $request = null): bool
    {
        $request ??= request();

        if ($this->matchesRequest($item, $request)) {
            return true;
        }

        $children = data_get($item, 'children');

        foreach (is_iterable($children) ? $children : [] as $child) {
            if ($this->isActive($child, $request)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>|MenuItem  $item
     */
    protected function matchesRequest(array|MenuItem $item, Request $request): bool
    {
        $target = $this->targetOf($item);

        if ($target === null) {
            return false;
        }

        if (Route::has($target)) {
            if ($request->routeIs($target)) {
                return true;
            }

            return str_ends_with($target, '.index')
                && $request->routeIs(substr($target, 0, -strlen('index')).'*');
        }

        if (preg_match('#^https?://#i', $target) === 1) {
            return rtrim($request->url(), '/') === rtrim(strtok($target, '?#'), '/');
        }

        if (str_contains($target, '/')) {
            $path = trim((string) parse_url($target, PHP_URL_PATH), '/');

            return $request->is($path === '' ? '/' : $path);
        }

        return false;
    }

    /**
     * @param  array<string, mixed>|MenuItem  $item
     */
    protected function targetOf(array|MenuItem $item): ?string
    {
        if (data_get($item, 'is_separator')) {
            return null;
        }

        $target = trim((string) data_get($item, 'route', ''));

        return $target === '' ? null : $target;
    }

    /*
    |--------------------------------------------------------------------------
    | Permissões
    |--------------------------------------------------------------------------
    */

    /**
     * O modo de permissão configurado: 'none', 'string' ou 'id'.
     *
     * @throws InvalidArgumentException se o valor da config for inválido.
     */
    public function permissionMode(): string
    {
        $mode = strtolower(trim((string) config('menu.permission_mode', 'none')));

        if ($mode === '') {
            return 'none';
        }

        if (! in_array($mode, self::MODES, true)) {
            throw new InvalidArgumentException(sprintf(
                "Valor inválido em config('menu.permission_mode') (MENU_PERMISSION_MODE): \"%s\". Usa 'none', 'string' ou 'id'.",
                $mode,
            ));
        }

        return $mode;
    }

    /**
     * O utilizador tem pelo menos uma destas permissões? No modo 'none'
     * devolve sempre true.
     *
     *   Menu::hasPermission('users.create');
     *   Menu::hasPermission(['users.create', 'users.edit']);  // qualquer uma
     *   Menu::hasPermission('users.create|users.edit');       // idem
     *
     * @param  string|iterable<string>  $permissions
     */
    public function hasPermission(string|iterable $permissions, mixed $user = null): bool
    {
        if ($this->permissionMode() === 'none') {
            return true;
        }

        $required = $this->splitPermissions($permissions);

        if ($required === []) {
            return true;
        }

        $owned = $this->lookup($this->resolveUserPermissions($user));

        foreach ($required as $permission) {
            if (isset($owned[$permission])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Um conjunto de permissões satisfaz a permissão exigida por um item?
     *
     * @internal Usado por MenuItem::isVisibleTo().
     *
     * @param  iterable<mixed>  $userPermissions
     */
    public function matchesPermission(mixed $required, iterable $userPermissions): bool
    {
        if ($this->permissionMode() === 'none' || $required === null || $required === '') {
            return true;
        }

        return isset($this->lookup($this->normalizePermissions($userPermissions))[(string) $required]);
    }

    /**
     * Define, em código, como obter as permissões de um utilizador. Tem
     * prioridade sobre config('menu.user_permissions') e, ao contrário de uma
     * closure na config, não impede o `php artisan config:cache`.
     *
     *   // app/Providers/AppServiceProvider.php, no método boot()
     *   Menu::resolvePermissionsUsing(fn ($user) => $user?->permissions->pluck('name') ?? []);
     *
     * Passa null para voltar ao comportamento padrão.
     */
    public function resolvePermissionsUsing(?callable $callback): void
    {
        $this->permissionsResolver = $callback;
    }

    /**
     * As permissões do utilizador (ou do utilizador autenticado).
     *
     * Ordem de procura:
     *  1. o resolver definido com resolvePermissionsUsing();
     *  2. config('menu.user_permissions'): uma fonte com nome (ver SOURCES)
     *     ou uma classe;
     *  3. sem nada configurado, descobre sozinho: primeiro o laravel-authz,
     *     depois $user->getAllPermissions() (Spatie);
     *  4. nenhuma permissão.
     *
     * @return list<int|string>
     */
    public function resolveUserPermissions(mixed $user = null): array
    {
        $user ??= auth()->user();

        if ($this->permissionsResolver !== null) {
            return $this->normalizePermissions(($this->permissionsResolver)($user));
        }

        $source = $this->configuredSource();

        if ($source !== null) {
            return match ($source) {
                'authz'  => $this->authzPermissions($user, explicit: true),
                'spatie' => $this->spatiePermissions($user, explicit: true),
                default  => $this->detectedPermissions($user),
            };
        }

        $resolver = $this->configuredResolver();

        if ($resolver !== null) {
            return $this->normalizePermissions($resolver($user));
        }

        return $this->detectedPermissions($user);
    }

    /**
     * Sem resolver configurado: descobre o pacote de permissões em uso a
     * partir do próprio model de utilizador, para que instalar o laravel-authz
     * (ou o Spatie) baste para o menu começar a esconder o que deve.
     *
     * @return list<int|string>
     */
    protected function detectedPermissions(mixed $user): array
    {
        // 1. Model com o trait HasAuthz/HasPermissions do laravel-authz.
        if (AuthzSupport::handles($user)) {
            return $this->authzPermissions($user);
        }

        // 2. getAllPermissions(): spatie/laravel-permission e compatíveis.
        if (is_object($user) && method_exists($user, 'getAllPermissions')) {
            return $this->normalizePermissions($user->getAllPermissions());
        }

        // 3. laravel-authz instalado, mas sem o trait no model: lê pelo id.
        if (AuthzSupport::available($user)) {
            return $this->authzPermissions($user);
        }

        return [];
    }

    /**
     * Permissões vindas do gsebastiao/laravel-authz, já com a cascata dele
     * aplicada: negações individuais, regras dos grupos, validade por datas e
     * tenant atual. No modo 'id' devolve ids; nos outros, nomes.
     *
     * @param  bool  $explicit  A fonte foi escolhida na config (e não detetada).
     * @return list<int|string>
     */
    protected function authzPermissions(mixed $user, bool $explicit = false): array
    {
        if ($explicit) {
            AuthzSupport::assertPackageInstalled();
        }

        return $this->normalizePermissions(AuthzSupport::permissionsFor($user, $this->permissionMode()));
    }

    /**
     * Permissões vindas de $user->getAllPermissions(): o
     * spatie/laravel-permission e qualquer model com esse método.
     *
     * @param  bool  $explicit  A fonte foi escolhida na config (e não detetada).
     * @return list<int|string>
     */
    protected function spatiePermissions(mixed $user, bool $explicit = false): array
    {
        if (is_object($user) && method_exists($user, 'getAllPermissions')) {
            return $this->normalizePermissions($user->getAllPermissions());
        }

        // Um visitante (null) não tem permissões, e isso não é um erro.
        if ($explicit && $user !== null) {
            throw new InvalidArgumentException(sprintf(
                "config('menu.user_permissions') está definida como 'spatie' (MENU_USER_PERMISSION=spatie), mas o ".
                'model %s não tem o método getAllPermissions(). Acrescenta-lhe o trait '.
                "Spatie\\Permission\\Traits\\HasRoles, ou escolhe outra fonte de permissões.",
                is_object($user) ? $user::class : get_debug_type($user),
            ));
        }

        return [];
    }

    /**
     * A fonte com nome escolhida em config('menu.user_permissions'), se for
     * uma das de SOURCES ('auto', 'authz', 'spatie'). Qualquer outro valor
     * (uma classe, um callable) é tratado pelo configuredResolver().
     */
    protected function configuredSource(): ?string
    {
        $source = config('menu.user_permissions');

        if (! is_string($source)) {
            return null;
        }

        $source = strtolower(trim($source));

        return in_array($source, self::SOURCES, true) ? $source : null;
    }

    /**
     * Lê config('menu.user_permissions'). Aceita:
     *  - uma fonte com nome:                    'auto', 'authz' ou 'spatie'
     *  - uma classe com __invoke($user):        App\Support\MenuPermissions::class
     *  - uma classe e um método:                [App\Support\MenuPermissions::class, 'resolve']
     *                                           ou 'App\Support\MenuPermissions@resolve'
     *  - qualquer outro callable (ex.: closure — funciona, mas impede o config:cache).
     *
     * @return (callable(mixed): mixed)|null
     */
    protected function configuredResolver(): ?callable
    {
        $resolver = config('menu.user_permissions');

        if ($resolver === null || $resolver === '' || $resolver === []) {
            return null;
        }

        if (is_string($resolver) && str_contains($resolver, '@')) {
            $resolver = explode('@', $resolver, 2);
        }

        if (is_array($resolver) && isset($resolver[0], $resolver[1]) && is_string($resolver[0]) && class_exists($resolver[0])) {
            $resolver = [app($resolver[0]), $resolver[1]];
        } elseif (is_string($resolver) && class_exists($resolver)) {
            $resolver = app($resolver);
        }

        if (! is_callable($resolver)) {
            throw new InvalidArgumentException(
                "config('menu.user_permissions') não é válido. Usa null, uma das fontes '".implode("', '", self::SOURCES)."', ".
                "uma classe com o método __invoke(\$user) (ex.: App\\Support\\MenuPermissions::class) ou [Classe::class, 'metodo']."
            );
        }

        return $resolver;
    }

    /**
     * Converte o que o resolver devolve numa lista simples de nomes (ou ids).
     * Aceita arrays, Collections, models, arrays associativos e enums.
     *
     * @return list<int|string>
     */
    protected function normalizePermissions(mixed $permissions): array
    {
        if (is_string($permissions) || is_int($permissions) || $permissions instanceof UnitEnum) {
            $permissions = [$permissions];
        }

        if (! is_iterable($permissions)) {
            return [];
        }

        $resolver = (array) config('menu.resolver', []);
        [$field, $fallback] = $this->permissionMode() === 'id'
            ? [$resolver['key'] ?? 'id', 'id']
            : [$resolver['column'] ?? 'name', 'name'];

        $result = [];

        foreach ($permissions as $permission) {
            $value = $this->permissionValue($permission, $field, $fallback);

            if ($value !== null && $value !== '') {
                $result[] = $value;
            }
        }

        return $result;
    }

    protected function permissionValue(mixed $permission, string $field, string $fallback): int|string|null
    {
        if ($permission instanceof BackedEnum) {
            return $permission->value;
        }

        if ($permission instanceof UnitEnum) {
            return $permission->name;
        }

        if (is_array($permission) || is_object($permission)) {
            $value = data_get($permission, $field) ?? data_get($permission, $fallback);

            if ($value === null && $permission instanceof Stringable) {
                return (string) $permission;
            }

            return is_int($value) || is_string($value) ? $value : null;
        }

        return is_int($permission) || is_string($permission) ? $permission : null;
    }

    /**
     * @param  string|iterable<string>  $permissions
     * @return list<string>
     */
    protected function splitPermissions(string|iterable $permissions): array
    {
        $result = [];

        foreach (is_string($permissions) ? [$permissions] : $permissions as $permission) {
            foreach (explode('|', (string) $permission) as $part) {
                $part = trim($part);

                if ($part !== '') {
                    $result[] = $part;
                }
            }
        }

        return $result;
    }

    /**
     * @param  iterable<int|string>  $permissions
     * @return array<string, true>
     */
    protected function lookup(iterable $permissions): array
    {
        $lookup = [];

        foreach ($permissions as $permission) {
            $lookup[(string) $permission] = true;
        }

        return $lookup;
    }

    /*
    |--------------------------------------------------------------------------
    | Cache
    |--------------------------------------------------------------------------
    */

    /**
     * Apaga a árvore da cache. Com a cache desligada, tenta apagar na mesma
     * (para não ficar uma árvore antiga guardada se a cache voltar a ser
     * ligada) e ignora erros de um store que nem está em uso.
     */
    public function flushCache(): void
    {
        try {
            $this->cache()->forget($this->cacheKey());
        } catch (Throwable $e) {
            if ($this->cacheEnabled()) {
                throw $e;
            }
        }
    }

    /**
     * Volta a ler os itens da base de dados e guarda a árvore na cache.
     *
     * @return Collection<int, array<string, mixed>>
     */
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
        // null, '' ou 0 = sem prazo. (Um prazo de 0 segundos faria o Laravel
        // apagar a entrada logo a seguir, e a cache nunca funcionaria.)
        $ttl = (int) config('menu.cache.ttl');

        if ($ttl <= 0) {
            $this->cache()->forever($this->cacheKey(), $tree->all());

            return;
        }

        $this->cache()->put($this->cacheKey(), $tree->all(), $ttl);
    }

    protected function cache(): CacheRepository
    {
        $store = config('menu.cache.store');

        return $store ? Cache::store($store) : Cache::store();
    }

    /**
     * A cache está ligada? Útil para avisar (em vez de enganar) quando se
     * manda reconstruir uma cache que está desligada.
     */
    public function cacheEnabled(): bool
    {
        return (bool) config('menu.cache.enabled', true);
    }

    protected function cacheKey(): string
    {
        return config('menu.cache.key', 'laravel_menu').':tree';
    }
}
