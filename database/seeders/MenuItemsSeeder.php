<?php

declare(strict_types=1);

namespace Gsebastiao\DynamicMenu\Database\Seeders;

use Gsebastiao\DynamicMenu\Models\MenuItem;
use Illuminate\Database\Seeder;

class MenuItemsSeeder extends Seeder
{
    public function run(): void
    {
        /** @var class-string<MenuItem> $model */
        $model = config('dynamic-menu.model', MenuItem::class);

        // Dashboard (raiz, sem permissão -> visível a todos)
        $dashboard = $model::create([
            'name'      => 'dashboard',
            'label'     => 'Dashboard',
            'route'     => 'dashboard',
            'icon'      => 'home',
            'order'     => 1,
            'is_active' => true,
        ]);

        // Administração (raiz, com permissão)
        $admin = $model::create([
            'name'       => 'admin',
            'label'      => 'Administração',
            'icon'       => 'shield',
            'order'      => 2,
            'permission' => 'admin.access',
            'is_active'  => true,
        ]);

        // Filhos de Administração
        $model::create([
            'parent_id'  => $admin->id,
            'name'       => 'admin.users',
            'label'      => 'Utilizadores',
            'route'      => 'admin.users.index',
            'icon'       => 'users',
            'order'      => 1,
            'permission' => 'users.view',
            'badge'      => 'novo',
        ]);

        $model::create([
            'parent_id'  => $admin->id,
            'name'       => 'admin.roles',
            'label'      => 'Perfis',
            'route'      => 'admin.roles.index',
            'icon'       => 'key',
            'order'      => 2,
            'permission' => 'roles.view',
        ]);

        // Separador dentro de Administração
        $model::create([
            'parent_id'    => $admin->id,
            'name'         => 'admin.sep.1',
            'label'        => '—',
            'order'        => 3,
            'is_separator' => true,
        ]);

        // Nível mais profundo: Definições > Sistema (demonstra N níveis)
        $settings = $model::create([
            'parent_id'  => $admin->id,
            'name'       => 'admin.settings',
            'label'      => 'Definições',
            'icon'       => 'cog',
            'order'      => 4,
            'permission' => 'settings.view',
        ]);

        $model::create([
            'parent_id'  => $settings->id,
            'name'       => 'admin.settings.system',
            'label'      => 'Sistema',
            'route'      => 'admin.settings.system',
            'icon'       => 'server',
            'order'      => 1,
            'permission' => 'settings.system',
            'params'     => ['section' => 'general'],
        ]);

        // Link externo de exemplo (target _blank)
        $model::create([
            'name'      => 'docs',
            'label'     => 'Documentação',
            'route'     => 'https://github.com/gsebastiao/laravel-dynamic-menu',
            'icon'      => 'book',
            'order'     => 3,
            'target'    => '_blank',
            'is_active' => true,
        ]);
    }
}
