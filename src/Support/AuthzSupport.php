<?php

declare(strict_types=1);

namespace Gsebastiao\LaravelMenu\Support;

use Gsebastiao\LaravelAuthz\Enums\PermissionFormat;
use LogicException;
use RuntimeException;

/**
 * Ponto único de verdade sobre a integração opcional com o pacote
 * gsebastiao/laravel-authz (grupos e permissões).
 *
 * O laravel-menu não depende (require) do laravel-authz — és tu quem decide
 * instalá-lo. Esta classe existe para que:
 *
 *   1. Com o laravel-authz instalado, o menu saiba sozinho quais são as
 *      permissões de quem está autenticado, sem escreveres um resolver.
 *   2. Pôr MENU_USER_PERMISSION=authz sem o pacote instalado dê um erro claro
 *      logo no arranque, em vez de um menu que esconde tudo em silêncio.
 *   3. O resto do laravel-menu nunca carregue classes
 *      `Gsebastiao\LaravelAuthz\...` quando o pacote não está instalado.
 *
 * O `use` acima não carrega nada: o PHP só procura a classe quando o código
 * que a usa corre, e isso só acontece com o pacote instalado.
 */
final class AuthzSupport
{
    /**
     * Serviço principal do laravel-authz. `::class` não carrega a classe, por
     * isso esta constante é segura mesmo sem o pacote instalado.
     */
    public const SERVICE = \Gsebastiao\LaravelAuthz\Models\Authorization::class;

    /** Trait que dá os métodos de permissão ao model de utilizador. */
    public const PERMISSIONS_TRAIT = \Gsebastiao\LaravelAuthz\Traits\HasPermissions::class;

    public const PACKAGE_NAME = 'gsebastiao/laravel-authz';

    public const INSTALL_COMMAND = 'composer require gsebastiao/laravel-authz';

    /** Valor de config('menu.user_permissions') que escolhe esta fonte. */
    public const SOURCE = 'authz';

    /**
     * O pacote gsebastiao/laravel-authz está instalado?
     */
    public static function packageInstalled(): bool
    {
        return class_exists(self::SERVICE);
    }

    /**
     * O model de utilizador traz os métodos do laravel-authz?
     *
     * Verifica o trait e, para quem tenha escrito os métodos à mão, a
     * presença dos dois. São precisos os DOIS: o spatie/laravel-permission
     * também tem `getPermissionNames()`, mas não tem `getPermissionIds()`.
     */
    public static function handles(mixed $user): bool
    {
        if (! is_object($user)) {
            return false;
        }

        if (in_array(self::PERMISSIONS_TRAIT, class_uses_recursive($user), true)) {
            return true;
        }

        return method_exists($user, 'getPermissionNames') && method_exists($user, 'getPermissionIds');
    }

    /**
     * Há por onde ler as permissões deste utilizador no laravel-authz? É o
     * caso quando o model tem o trait ou quando, sem ele, o pacote está
     * instalado e o utilizador tem uma chave numérica (o laravel-authz guarda
     * as concessões por `user_id`).
     */
    public static function available(mixed $user): bool
    {
        return self::handles($user)
            || (self::packageInstalled() && self::userId($user) !== null);
    }

    /**
     * As permissões efetivas do utilizador, já com a cascata do laravel-authz
     * aplicada: negações individuais, regras dos grupos, validade por datas e
     * tenant atual. Um visitante (null) não tem nenhuma.
     *
     * @param  string  $mode  O modo de permissão do menu: 'id' devolve ids, o resto nomes.
     * @return list<int|string>
     */
    public static function permissionsFor(mixed $user, string $mode): array
    {
        if (self::handles($user)) {
            return array_values($mode === 'id' ? $user->getPermissionIds() : $user->getPermissionNames());
        }

        $id = self::userId($user);

        if ($id === null || ! self::packageInstalled()) {
            return [];
        }

        return array_values(app(self::SERVICE)->getEffectivePermissions(
            $id,
            $mode === 'id' ? PermissionFormat::Id : PermissionFormat::Permission,
        ));
    }

    /**
     * Valida a configuração. Chamado no boot do MenuServiceProvider.
     *
     * @throws RuntimeException se a fonte 'authz' estiver escolhida sem o pacote.
     */
    public static function assertConfigured(): void
    {
        $source = config('menu.user_permissions');

        if (is_string($source) && strtolower(trim($source)) === self::SOURCE && ! self::packageInstalled()) {
            throw new RuntimeException(
                "O laravel-menu está configurado para ler as permissões do '".self::PACKAGE_NAME."' ".
                "(MENU_USER_PERMISSION=authz), mas esse pacote não está instalado.\n\n".
                self::installInstructions().
                "Se não usas esse pacote, remove a variável MENU_USER_PERMISSION do .env (ou põe-lhe o valor 'auto')."
            );
        }
    }

    /**
     * @throws LogicException se o pacote não estiver instalado.
     */
    public static function assertPackageInstalled(): void
    {
        if (! self::packageInstalled()) {
            throw new LogicException(
                "Ler as permissões do menu a partir do '".self::PACKAGE_NAME."' precisa desse pacote instalado.\n\n".
                self::installInstructions()
            );
        }
    }

    /**
     * O id do utilizador, se for numérico. O laravel-authz liga as permissões
     * a `user_id` (inteiro), por isso chaves não numéricas (ex.: uuid) não
     * servem para o consultar.
     */
    private static function userId(mixed $user): ?int
    {
        if (! is_object($user) || ! method_exists($user, 'getKey')) {
            return null;
        }

        $key = $user->getKey();

        return is_numeric($key) ? (int) $key : null;
    }

    private static function installInstructions(): string
    {
        return "Instala-o com:\n\n".
            '    '.self::INSTALL_COMMAND."\n".
            "    php artisan authz:install\n\n".
            "Documentação: https://github.com/gsebastiao/laravel-authz\n\n";
    }
}
