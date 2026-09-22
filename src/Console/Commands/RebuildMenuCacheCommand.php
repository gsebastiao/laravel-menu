<?php

declare(strict_types=1);

namespace Gsebastiao\LaravelMenu\Console\Commands;

use Gsebastiao\LaravelMenu\Services\MenuManager;
use Illuminate\Console\Command;

class RebuildMenuCacheCommand extends Command
{
    protected $signature = 'laravel-menu:cache
                            {--flush : Apenas limpar a cache, sem a reconstruir}';

    protected $description = 'Reconstrói (ou limpa, com --flush) a cache da árvore de menus';

    public function handle(MenuManager $manager): int
    {
        if ($this->option('flush')) {
            $manager->flushCache();
            $this->info('Cache de menus limpa com sucesso.');

            return self::SUCCESS;
        }

        $tree = $manager->rebuildCache();

        if (! $manager->cacheEnabled()) {
            // Sem este aviso, o comando dizia "reconstruída" e a cache
            // continuava desligada — e a culpa do menu lento ficava por achar.
            $this->warn(
                'A cache está desligada (MENU_CACHE_ENABLED=false): nada foi guardado. '.
                'Liga-a para o menu deixar de ir à base de dados em cada página.'
            );

            return self::SUCCESS;
        }

        $this->info(sprintf(
            'Cache de menus reconstruída: %d item(ns) de raiz.',
            $tree->count()
        ));

        return self::SUCCESS;
    }
}
