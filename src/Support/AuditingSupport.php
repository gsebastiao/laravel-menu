<?php

declare(strict_types=1);

namespace Gsebastiao\LaravelMenu\Support;

use Illuminate\Database\Eloquent\Model;
use LogicException;
use RuntimeException;

/**
 * Ponto único de verdade sobre a integração opcional com o pacote
 * gsebastiao/laravel-auditable.
 *
 * O laravel-menu não depende (require) do laravel-auditable — és tu quem
 * decide instalá-lo. Esta classe existe para que:
 *
 *   1. Ligar `menu.auditing.enabled` sem o pacote instalado dê um erro claro
 *      logo no arranque, em vez de uma auditoria que parece ligada mas não
 *      grava nada.
 *   2. O resto do laravel-menu nunca carregue classes `Gsebastiao\Auditable\...`
 *      quando o pacote não está instalado.
 */
final class AuditingSupport
{
    /**
     * Trait principal do pacote de auditoria. `::class` não carrega a classe,
     * por isso esta constante é segura mesmo sem o pacote instalado.
     */
    public const AUDITABLE_TRAIT = \Gsebastiao\Auditable\Concerns\Auditable::class;

    public const PACKAGE_NAME = 'gsebastiao/laravel-auditable';

    public const INSTALL_COMMAND = 'composer require gsebastiao/laravel-auditable';

    /**
     * A auditoria está ligada na config?
     */
    public static function enabled(): bool
    {
        return (bool) config('menu.auditing.enabled', false);
    }

    /**
     * O pacote gsebastiao/laravel-auditable está instalado?
     */
    public static function packageInstalled(): bool
    {
        return trait_exists(self::AUDITABLE_TRAIT);
    }

    /**
     * Auditoria ligada na config E pacote instalado.
     */
    public static function shouldAudit(): bool
    {
        return self::enabled() && self::packageInstalled();
    }

    /**
     * A classe (ou alguma classe-mãe) usa o trait Auditable do pacote?
     */
    public static function usesAuditableTrait(object|string $model): bool
    {
        return in_array(self::AUDITABLE_TRAIT, class_uses_recursive($model), true);
    }

    /**
     * Grava um evento automático (created, updated, deleted, restored) de um
     * item de menu. Não faz nada se a auditoria estiver desligada.
     */
    public static function record(Model $model, string $event): void
    {
        if (! self::shouldAudit()) {
            return;
        }

        if (! $model->getAuditOptions()->allowsEvent($event)) {
            return;
        }

        app(\Gsebastiao\Auditable\AuditManager::class)->record($model, $event);
    }

    /**
     * Valida a configuração. Chamado no boot do MenuServiceProvider.
     *
     * @throws RuntimeException se a auditoria estiver ligada sem o pacote.
     */
    public static function assertConfigured(): void
    {
        if (self::enabled() && ! self::packageInstalled()) {
            throw new RuntimeException(
                "A auditoria do laravel-menu está ativada (MENU_AUDITING_ENABLED=true), ".
                "mas o pacote '".self::PACKAGE_NAME."' não está instalado.\n\n".
                self::installInstructions().
                "Se não quiseres auditoria, define MENU_AUDITING_ENABLED=false (ou remove a variável) no .env."
            );
        }
    }

    /**
     * @throws LogicException se o pacote de auditoria não estiver instalado.
     */
    public static function assertPackageInstalled(): void
    {
        if (! self::packageInstalled()) {
            throw new LogicException(
                "O histórico de auditoria (\$item->audits) precisa do pacote '".self::PACKAGE_NAME."'.\n\n".
                self::installInstructions()
            );
        }
    }

    private static function installInstructions(): string
    {
        return "Instala-o com:\n\n".
            '    '.self::INSTALL_COMMAND."\n".
            "    php artisan vendor:publish --tag=auditable-config\n".
            "    php artisan vendor:publish --tag=auditable-migrations\n".
            "    php artisan migrate\n\n".
            "Documentação: https://github.com/gsebastiao/laravel-auditable\n\n";
    }
}
