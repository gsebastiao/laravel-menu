<?php

declare(strict_types=1);

namespace Gsebastiao\DynamicMenu\Console\Commands;

use Gsebastiao\DynamicMenu\Services\MenuManager;
use Illuminate\Console\Command;

class RebuildMenuCacheCommand extends Command
{
    protected $signature = 'dynamic-menu:cache
                            {--flush : Apenas limpar a cache, sem reconstruir}';

    protected $description = 'Reconstrói (ou limpa) a cache da árvore de menus dinâmicos';

    public function handle(MenuManager $manager): int
    {
        if ($this->option('flush')) {
            $manager->flushCache();
            $this->info('Cache de menus limpa com sucesso.');

            return self::SUCCESS;
        }

        $tree = $manager->rebuildCache();

        $this->info(sprintf(
            'Cache de menus reconstruída: %d item(ns) de raiz.',
            $tree->count()
        ));

        return self::SUCCESS;
    }
}
