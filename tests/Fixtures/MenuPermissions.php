<?php

declare(strict_types=1);

namespace Gsebastiao\LaravelMenu\Tests\Fixtures;

/**
 * Resolver de permissões para config('menu.user_permissions').
 */
class MenuPermissions
{
    public function __invoke(mixed $user): array
    {
        return ['via.invoke'];
    }

    public function resolve(mixed $user): array
    {
        return ['via.metodo'];
    }
}
