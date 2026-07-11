<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Modo de Permissão
    |--------------------------------------------------------------------------
    |
    | Define como o campo `permission` de cada item de menu é interpretado.
    | O pacote é 100% independente: não depende de Spatie nem de nenhum outro
    | pacote de permissões.
    |
    | Suportados:
    |
    |   'none'   -> Projeto simples, SEM permissões. O campo `permission` é
    |               ignorado e todos os itens passam no middleware. Default.
    |
    |   'string' -> O campo `permission` guarda a permissão como texto
    |               (ex.: "user.create"). O middleware compara essa string
    |               com as permissões do utilizador (ver 'user_permissions').
    |
    |   'id'     -> O campo `permission` guarda um id (como texto, ex.: "42")
    |               que aponta para a tabela definida em 'resolver'. O pacote
    |               resolve esse id contra a tabela para validar/exibir.
    |
    */

    'permission_mode' => env('DYNAMIC_MENU_PERMISSION_MODE', 'none'),

    /*
    |--------------------------------------------------------------------------
    | Resolver (apenas para permission_mode = 'id')
    |--------------------------------------------------------------------------
    |
    | Aponta para QUALQUER tabela do teu projeto. O pacote não conhece nem
    | assume nada sobre ela — tu ligas a tua própria tabela aqui.
    |
    |   table  -> nome da tabela de permissões do projeto
    |   key    -> coluna comparada com o valor guardado em `permission`
    |   column -> coluna usada para exibição/validação (ex.: nome legível)
    |
    | Ignorado quando permission_mode é 'none' ou 'string'.
    |
    */

    'resolver' => [
        'table'  => env('DYNAMIC_MENU_RESOLVER_TABLE', 'permissions'),
        'key'    => env('DYNAMIC_MENU_RESOLVER_KEY', 'id'),
        'column' => env('DYNAMIC_MENU_RESOLVER_COLUMN', 'name'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Resolução das Permissões do Utilizador
    |--------------------------------------------------------------------------
    |
    | Callback que devolve a lista de permissões do utilizador autenticado,
    | usada pelo middleware e pelo Menu::forUser(). Recebe o utilizador
    | (ou null) e deve devolver um array/Collection de strings ou ids.
    |
    | Por omissão tenta o método getAllPermissions() (compatível com muitos
    | setups, incl. Spatie) e faz fallback para um array vazio. Podes
    | substituir por qualquer lógica do teu projeto.
    |
    | Aceita: null (usa o default do pacote) ou um callable.
    |
    */

    'user_permissions' => null,

    /*
    |--------------------------------------------------------------------------
    | Cache
    |--------------------------------------------------------------------------
    |
    | A árvore de menus é lida sem joins a partir da cache. É invalidada
    | automaticamente sempre que um item é criado, atualizado ou removido.
    |
    |   enabled -> liga/desliga a cache
    |   store   -> qual o cache store (null = default da app; ex.: 'redis')
    |   key     -> prefixo das chaves de cache
    |   ttl     -> tempo de vida em segundos (null = para sempre / forever)
    |
    */

    'cache' => [
        'enabled' => env('DYNAMIC_MENU_CACHE_ENABLED', true),
        'store'   => env('DYNAMIC_MENU_CACHE_STORE', null),
        'key'     => 'dynamic_menu',
        'ttl'     => env('DYNAMIC_MENU_CACHE_TTL', null),
    ],

    /*
    |--------------------------------------------------------------------------
    | Tabela
    |--------------------------------------------------------------------------
    |
    | Nome da tabela onde os itens de menu são guardados.
    |
    */

    'table' => 'menu_items',

    /*
    |--------------------------------------------------------------------------
    | Model
    |--------------------------------------------------------------------------
    |
    | Model usado internamente. Podes trocar por uma subclasse tua se quiseres
    | estender comportamento.
    |
    */

    'model' => \Gsebastiao\DynamicMenu\Models\MenuItem::class,

];
