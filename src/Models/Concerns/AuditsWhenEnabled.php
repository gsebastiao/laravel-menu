<?php

declare(strict_types=1);

namespace Gsebastiao\LaravelMenu\Models\Concerns;

use Gsebastiao\Auditable\Support\AuditOptions;
use Gsebastiao\Auditable\Support\ResolveMap;
use Gsebastiao\LaravelMenu\Support\AuditingSupport;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Integração OPCIONAL com o pacote gsebastiao/laravel-auditable.
 *
 * O laravel-menu não depende do pacote de auditoria. Este trait só usa as
 * classes dele quando as duas condições se verificam:
 *
 *   - config('menu.auditing.enabled') é true (MENU_AUDITING_ENABLED=true);
 *   - o pacote gsebastiao/laravel-auditable está instalado.
 *
 * Nesse caso, cada create, update, delete e restore de um item de menu é
 * gravado na auditoria, com o `parent_id` traduzido para o nome do item pai.
 * Funciona com MenuItem::create(), $item->update(), route model binding,
 * etc. — não é preciso mudar a forma como gravas os itens.
 *
 * Os `use` acima não carregam nada: o PHP só procura essas classes quando o
 * código que as usa é executado, e isso só acontece com a auditoria ativa.
 */
trait AuditsWhenEnabled
{
    public static function bootAuditsWhenEnabled(): void
    {
        // Uma subclasse que já usa o trait Auditable do pacote grava a sua
        // própria auditoria. Registar os eventos aqui duplicaria as linhas.
        if (AuditingSupport::usesAuditableTrait(static::class)) {
            return;
        }

        foreach (['created', 'updated', 'deleted', 'restored'] as $event) {
            static::registerModelEvent($event, static function (self $item) use ($event): void {
                AuditingSupport::record($item, $event);
            });
        }
    }

    /**
     * O que é gravado na auditoria. Para mudar, cria uma subclasse de
     * MenuItem, sobrescreve este método e aponta config('menu.model') para
     * ela (ver a secção "Auditoria" do README).
     */
    public function getAuditOptions(): AuditOptions
    {
        return AuditOptions::defaults()
            ->events(['created', 'updated', 'deleted', 'restored'])
            ->resolveMap([
                'parent_id' => ResolveMap::direct(
                    label: 'Item pai',
                    table: $this->getTable(),
                    column: 'label',
                ),
            ])
            ->except(['params']);
    }

    /**
     * Histórico de auditoria deste item: $item->audits.
     *
     * Exige o pacote gsebastiao/laravel-auditable instalado.
     */
    public function audits(): MorphMany
    {
        AuditingSupport::assertPackageInstalled();

        return $this->morphMany(config('auditable.model'), 'subject');
    }
}
