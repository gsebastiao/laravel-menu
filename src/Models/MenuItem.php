<?php

declare(strict_types=1);

namespace Gsebastiao\LaravelMenu\Models;

use Gsebastiao\LaravelMenu\Services\MenuManager;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
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
    use SoftDeletes;

    protected $guarded = ['id'];

    protected $casts = [
        'parent_id'    => 'integer',
        'order'        => 'integer',
        'is_active'    => 'boolean',
        'is_separator' => 'boolean',
        'params'       => 'array',
    ];

    public function getTable(): string
    {
        return config('menu.table', 'menu_items');
    }

    /*
    |--------------------------------------------------------------------------
    | Eventos: invalidação automática de cache
    |--------------------------------------------------------------------------
    */

    protected static function booted(): void
    {
        $flush = static function (): void {
            app(MenuManager::class)->flushCache();
        };

        static::saved($flush);
        static::deleted($flush);

        if (method_exists(static::class, 'restored')) {
            static::restored($flush);
        }

        if (method_exists(static::class, 'forceDeleted')) {
            static::forceDeleted($flush);
        }
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

    public function children(): HasMany
    {
        return $this->hasMany(static::class, 'parent_id')->orderBy('order');
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
        return $query->orderBy('order')->orderBy('id');
    }

    /*
    |--------------------------------------------------------------------------
    | Permissões
    |--------------------------------------------------------------------------
    */

    /**
     * Indica se este item é visível dado o conjunto de permissões do
     * utilizador. A interpretação depende de config('menu.permission_mode').
     *
     * @param  array<int|string>  $userPermissions
     */
    public function isVisibleTo(array $userPermissions): bool
    {
        if (! $this->is_active) {
            return false;
        }

        $mode = config('menu.permission_mode', 'none');

        // Separadores e itens sem permissão passam sempre.
        if ($mode === 'none' || $this->permission === null || $this->permission === '') {
            return true;
        }

        // Comparação por valor bruto (string ou id-como-texto).
        // Normalizamos ambos os lados para string para uma comparação segura.
        $needle = (string) $this->permission;

        $haystack = array_map(static fn($p) => (string) $p, $userPermissions);

        return in_array($needle, $haystack, true);
    }

    /**
     * Devolve o valor legível da permissão. No modo 'id', resolve contra a
     * tabela configurada; caso contrário devolve a própria string.
     */
    public function resolvedPermissionLabel(): ?string
    {
        if ($this->permission === null || $this->permission === '') {
            return null;
        }

        if (config('menu.permission_mode') !== 'id') {
            return (string) $this->permission;
        }

        $resolver = config('menu.resolver');

        $value = \Illuminate\Support\Facades\DB::table($resolver['table'])
            ->where($resolver['key'], $this->permission)
            ->value($resolver['column']);

        return $value !== null ? (string) $value : null;
    }
}
