<?php

declare(strict_types=1);

namespace Gsebastiao\DynamicMenu\Facades;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Facade;

/**
 * @method static Collection tree()
 * @method static Collection forUser(mixed $user = null, ?array $userPermissions = null)
 * @method static Collection buildTree()
 * @method static Collection rebuildCache()
 * @method static void flushCache()
 * @method static array resolveUserPermissions(mixed $user = null)
 *
 * @see \Gsebastiao\DynamicMenu\Services\MenuManager
 */
class Menu extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Gsebastiao\DynamicMenu\Services\MenuManager::class;
    }
}
