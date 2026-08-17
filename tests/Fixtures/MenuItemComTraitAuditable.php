<?php

declare(strict_types=1);

namespace Gsebastiao\LaravelMenu\Tests\Fixtures;

use Gsebastiao\Auditable\Concerns\Auditable;
use Gsebastiao\LaravelMenu\Models\MenuItem;

/**
 * Subclasse que usa diretamente o trait do pacote de auditoria.
 * Só é carregada nos testes que exigem o pacote instalado.
 */
class MenuItemComTraitAuditable extends MenuItem
{
    use Auditable;
}
