<?php

declare(strict_types=1);

namespace Gsebastiao\LaravelMenu\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Illuminate\Support\Collection tree()
 * @method static \Illuminate\Support\Collection forUser(mixed $user = null, iterable|null $userPermissions = null)
 * @method static \Illuminate\Support\Collection buildTree()
 * @method static string|null url(array|\Gsebastiao\LaravelMenu\Models\MenuItem $item)
 * @method static bool isActive(array|\Gsebastiao\LaravelMenu\Models\MenuItem $item, \Illuminate\Http\Request|null $request = null)
 * @method static bool hasPermission(string|iterable $permissions, mixed $user = null)
 * @method static string permissionMode()
 * @method static void resolvePermissionsUsing(callable|null $callback)
 * @method static array resolveUserPermissions(mixed $user = null)
 * @method static \Illuminate\Support\Collection rebuildCache()
 * @method static void flushCache()
 * @method static bool cacheEnabled()
 *
 * @see \Gsebastiao\LaravelMenu\Services\MenuManager
 */
class Menu extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Gsebastiao\LaravelMenu\Services\MenuManager::class;
    }
}
