<?php

declare(strict_types=1);

namespace Gsebastiao\LaravelMenu\Tests\Fixtures;

use Gsebastiao\Auditable\Support\AuditOptions;
use Gsebastiao\LaravelMenu\Models\MenuItem;

/**
 * Subclasse com opções de auditoria próprias (sem usar o trait Auditable).
 */
class MenuItemPersonalizado extends MenuItem
{
    public function getAuditOptions(): AuditOptions
    {
        return parent::getAuditOptions()->except(['badge']);
    }
}
