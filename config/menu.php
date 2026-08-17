<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Modo de permissão
    |--------------------------------------------------------------------------
    |
    | Diz ao pacote como ler a coluna `permission` de cada item de menu.
    |
    |   'none'   -> Sem permissões (padrão). Todos os itens aparecem a toda a
    |               gente e o middleware `menu.permission` deixa passar sempre.
    |
    |   'string' -> A coluna guarda o NOME da permissão, ex.: "users.view".
    |               O item só aparece a quem tiver essa permissão.
    |
    |   'id'     -> A coluna guarda o ID da permissão, ex.: "42", de uma tabela
    |               do teu projeto (ver 'resolver' abaixo).
    |
    | Em qualquer modo, um item com `permission` vazia aparece a todos.
    |
    */

    'permission_mode' => env('MENU_PERMISSION_MODE', 'none'),

    /*
    |--------------------------------------------------------------------------
    | Tabela de permissões (só para o modo 'id')
    |--------------------------------------------------------------------------
    |
    | Aponta para a tabela de permissões do TEU projeto. O pacote não cria nem
    | exige nenhuma tabela: só a lê para mostrar o nome de uma permissão.
    |
    |   table  -> nome da tabela (ex.: 'permissions' no spatie/laravel-permission)
    |   key    -> coluna com o valor guardado em `permission` (normalmente 'id')
    |   column -> coluna com o nome legível da permissão (ex.: 'name')
    |
    */

    'resolver' => [
        'key'    => env('MENU_RESOLVER_KEY', 'id'),
        'column' => env('MENU_RESOLVER_COLUMN', 'name'),
        'table'  => env('MENU_RESOLVER_TABLE', 'auth_permissions'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Permissões do utilizador
    |--------------------------------------------------------------------------
    |
    | Como o pacote descobre as permissões de quem está autenticado. É usado
    | por Menu::forUser(), Menu::hasPermission() e pelo middleware.
    |
    |   null -> (padrão) usa $user->getAllPermissions() se o teu model User
    |           tiver esse método (é o caso com spatie/laravel-permission);
    |           caso contrário, o utilizador fica sem permissões.
    |
    | Para usar a tua própria lógica, indica uma classe com o método
    | __invoke($user) que devolva os nomes (ou ids) das permissões:
    |
    |   'user_permissions' => \App\Support\MenuPermissions::class,
    |
    | Também podes defini-la em código, no AppServiceProvider:
    |
    |   Menu::resolvePermissionsUsing(fn ($user) => ...);
    |
    | Atenção: não escrevas uma função (closure) diretamente aqui. Funciona,
    | mas faz o `php artisan config:cache` falhar.
    |
    */

    'user_permissions' => env('MENU_USER_PERMISSION', null),

    /*
    |--------------------------------------------------------------------------
    | Cache
    |--------------------------------------------------------------------------
    |
    | A árvore de menus fica em cache para não ir à base de dados em cada
    | página. A cache é limpa sozinha quando um item é criado, editado,
    | apagado ou restaurado através do model.
    |
    |   enabled -> liga/desliga a cache
    |   store   -> que cache usar (null = a cache padrão da aplicação;
    |              ex.: 'redis', 'file')
    |   ttl     -> validade em segundos (null, vazio ou 0 = sem prazo)
    |   key     -> prefixo da chave na cache
    |
    */

    'cache' => [
        'enabled' => env('MENU_CACHE_ENABLED', true),
        'store'   => env('MENU_CACHE_STORE'),
        'ttl'     => env('MENU_CACHE_TTL'),
        'key'     => env('MENU_CACHE_KEY', 'laravel_menu'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Tabela dos itens de menu
    |--------------------------------------------------------------------------
    |
    | Se mudares isto, muda-o ANTES de correr `php artisan migrate`: a
    | migration publicada e o model leem o nome da tabela daqui.
    |
    */

    'table' => env('MENU_TABLE', 'menu_items'),

    /*
    |--------------------------------------------------------------------------
    | Model
    |--------------------------------------------------------------------------
    |
    | Model usado para montar a árvore e pelo seeder. Para acrescentar
    | comportamento, cria uma classe que estenda o MenuItem do pacote e
    | indica-a aqui.
    |
    */

    'model' => env('MENU_MODEL', \Gsebastiao\LaravelMenu\Models\MenuItem::class),

    /*
    |--------------------------------------------------------------------------
    | Auditoria (opcional)
    |--------------------------------------------------------------------------
    |
    | Com o pacote gsebastiao/laravel-auditable instalado, ligar isto grava
    | quem criou, editou, apagou ou restaurou cada item de menu, e quando.
    | O laravel-menu não depende desse pacote: instala-o só se quiseres.
    |
    | Se ligares isto SEM o pacote instalado, a aplicação pára no arranque
    | com uma mensagem a explicar como o instalar.
    |
    */

    'auditing' => [
        'enabled' => env('MENU_AUDITING_ENABLED', false),
    ],

];
