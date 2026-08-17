<?php

declare(strict_types=1);

namespace Gsebastiao\LaravelMenu\Models;

use Gsebastiao\LaravelMenu\Models\Concerns\AuditsWhenEnabled;
use Gsebastiao\LaravelMenu\Services\MenuManager;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

/**
 * Um item de menu. Os itens formam uma árvore através de `parent_id`.
 *
 * @property int         $id
 * @property int|null    $parent_id
 * @property string      $name
 * @property string      $label
 * @property string|null $description
 * @property string|null $route
 * @property string|null $icon
 * @property string|null $permission
 * @property int         $order
 * @property string|null $target
 * @property bool        $is_active
 * @property bool        $is_separator
 * @property string|null $badge
 * @property array|null  $params
 */
class MenuItem extends Model
{
    use AuditsWhenEnabled;
    use SoftDeletes;

    protected $guarded = ['id'];

    /**
     * Valores por omissão, iguais aos da migration. Sem isto, um item acabado
     * de criar com create() ficava com is_active = null em memória (a base de
     * dados aplica o default, mas o model não o conhece) e isVisibleTo()
     * devolvia false.
     */
    protected $attributes = [
        'order'        => 0,
        'is_active'    => true,
        'is_separator' => false,
    ];

    protected $casts = [
        'parent_id'    => 'integer',
        'order'        => 'integer',
        'is_active'    => 'boolean',
        'is_separator' => 'boolean',
        'params'       => 'array',
    ];

    /**
     * Usa config('menu.table'), a menos que uma subclasse defina $table.
     */
    public function getTable(): string
    {
        return $this->table ?? config('menu.table', 'menu_items');
    }

    /*
    |--------------------------------------------------------------------------
    | Eventos: invalidação automática da cache
    |--------------------------------------------------------------------------
    */

    protected static function booted(): void
    {
        $flush = static function (): void {
            app(MenuManager::class)->flushCache();
        };

        // `deleted` também dispara no forceDelete().
        static::saved($flush);
        static::deleted($flush);
        static::restored($flush);
    }

    /*
    |--------------------------------------------------------------------------
    | Relações de árvore (N níveis)
    |--------------------------------------------------------------------------
    */

    public function parent(): BelongsTo
    {
        return $this->belongsTo(static::class, 'parent_id');
    }

    /**
     * Filhos diretos, pela mesma ordem usada na árvore (order, depois id).
     */
    public function children(): HasMany
    {
        return $this->hasMany(static::class, 'parent_id')->ordered();
    }

    /**
     * Filhos recursivos (subárvore completa).
     */
    public function childrenRecursive(): HasMany
    {
        return $this->children()->with('childrenRecursive');
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeRoots(Builder $query): Builder
    {
        return $query->whereNull('parent_id');
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('order')->orderBy($this->getKeyName());
    }

    /*
    |--------------------------------------------------------------------------
    | Permissões
    |--------------------------------------------------------------------------
    */

    /**
     * Este item é visível para alguém com estas permissões?
     *
     * Um item inativo nunca é visível. Um item sem `permission` é visível para
     * todos. No modo 'none', todos os itens ativos são visíveis.
     *
     * @param  iterable<mixed>  $userPermissions
     */
    public function isVisibleTo(iterable $userPermissions): bool
    {
        if (! $this->is_active) {
            return false;
        }

        return app(MenuManager::class)->matchesPermission($this->permission, $userPermissions);
    }

    /**
     * Nome legível da permissão. No modo 'id', lê-o da tabela configurada em
     * config('menu.resolver'); nos outros modos devolve o próprio valor.
     */
    public function resolvedPermissionLabel(): ?string
    {
        if ($this->permission === null || $this->permission === '') {
            return null;
        }

        if (app(MenuManager::class)->permissionMode() !== 'id') {
            return (string) $this->permission;
        }

        $resolver = config('menu.resolver');

        $value = DB::table($resolver['table'])
            ->where($resolver['key'], $this->permission)
            ->value($resolver['column']);

        return $value !== null ? (string) $value : null;
    }
}
