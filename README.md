# Laravel Dynamic Menu

Menus **dinâmicos e hierárquicos** (N níveis) para Laravel, com controlo de permissões **flexível** e cache para leitura rápida sem joins.

O pacote é **100% independente**: não depende de Spatie nem de nenhum outro pacote de permissões. Podes usá-lo num projeto simples **sem permissões**, com **permissões por string**, ou apontando para **qualquer tabela** de permissões do teu projeto.

[![tests](https://github.com/gsebastiao/laravel-dynamic-menu/actions/workflows/tests.yml/badge.svg)](https://github.com/gsebastiao/laravel-dynamic-menu/actions)
[![Latest Version](https://img.shields.io/packagist/v/gsebastiao/laravel-dynamic-menu.svg)](https://packagist.org/packages/gsebastiao/laravel-dynamic-menu)
[![License](https://img.shields.io/packagist/l/gsebastiao/laravel-dynamic-menu.svg)](LICENSE.md)

---

## Índice

- [Requisitos](#requisitos)
- [Instalação](#instalação)
- [Conceito central: os três modos de permissão](#conceito-central-os-três-modos-de-permissão)
- [Configuração](#configuração)
- [Estrutura de um item de menu](#estrutura-de-um-item-de-menu)
- [Uso](#uso)
  - [Ler a árvore completa](#ler-a-árvore-completa)
  - [Filtrar pela permissão do utilizador](#filtrar-pela-permissão-do-utilizador)
  - [Renderizar em Blade](#renderizar-em-blade)
  - [Criar itens](#criar-itens)
- [Modos de permissão em detalhe](#modos-de-permissão-em-detalhe)
- [Middleware](#middleware)
- [Cache](#cache)
- [Seeder](#seeder)
- [Testes](#testes)
- [Licença](#licença)

---

## Requisitos

| Pacote  | Versão |
|---------|--------|
| PHP     | ^8.2   |
| Laravel | 11, 12 ou 13 |

> **Nota sobre Laravel 13 e PHP:** o Laravel 13 exige **PHP 8.3** no mínimo. Além disso, algumas versões de patch (13.3+) puxam componentes do Symfony 8 que exigem **PHP 8.4**. Se estiveres em PHP 8.3, considera fixar `laravel/framework:^13.0 <13.3` até poderes atualizar o runtime. Em PHP 8.2 usa Laravel 11 ou 12.

---

## Instalação

```bash
composer require gsebastiao/laravel-dynamic-menu
```

Publica a configuração (opcional, mas recomendado):

```bash
php artisan vendor:publish --tag=dynamic-menu-config
```

Corre as migrations (a tabela `menu_items` é registada automaticamente pelo pacote):

```bash
php artisan migrate
```

Se preferires **versionar/editar** a migration no teu projeto:

```bash
php artisan vendor:publish --tag=dynamic-menu-migrations
```

O Service Provider e a Facade `Menu` são registados automaticamente via package auto-discovery.

---

## Conceito central: os três modos de permissão

Cada item de menu tem **uma única coluna** `permission` (nullable). Como ela é interpretada depende do `permission_mode` no config:

| Modo      | O que guardas em `permission` | Quando usar |
|-----------|-------------------------------|-------------|
| `none`    | (ignorado)                    | Projeto simples, **sem permissões**. É o default. |
| `string`  | Texto, ex.: `user.create`     | Queres permissões mas **sem depender de FK**. |
| `id`      | Um id, ex.: `42`              | Queres apontar para **a tua tabela** de permissões. |

A mesma coluna carrega ora uma string ora um id — o config é que decide a leitura. Simples e flexível.

---

## Configuração

Ficheiro `config/dynamic-menu.php` (resumo dos campos principais):

```php
return [
    // 'none' | 'string' | 'id'
    'permission_mode' => env('DYNAMIC_MENU_PERMISSION_MODE', 'none'),

    // Usado apenas no modo 'id'. Aponta para QUALQUER tabela do teu projeto.
    'resolver' => [
        'table'  => 'permissions',
        'key'    => 'id',
        'column' => 'name',
    ],

    // Callback opcional que devolve as permissões do utilizador.
    // null => o pacote tenta $user->getAllPermissions() e faz fallback para [].
    'user_permissions' => null,

    'cache' => [
        'enabled' => true,
        'store'   => null,   // null = store default (podes pôr 'redis')
        'key'     => 'dynamic_menu',
        'ttl'     => null,   // null = forever
    ],

    'table' => 'menu_items',
    'model' => \Gsebastiao\DynamicMenu\Models\MenuItem::class,
];
```

---

## Estrutura de um item de menu

| Campo          | Tipo          | Descrição |
|----------------|---------------|-----------|
| `id`           | bigint        | PK |
| `parent_id`    | bigint, null  | Pai na hierarquia (N níveis) |
| `name`         | string        | Identificador interno/máquina (ex.: `admin.users`) |
| `label`        | string        | Texto exibido |
| `description`  | string, null  | Descrição opcional |
| `route`        | string, null  | Nome de rota, URL ou caminho |
| `icon`         | string, null  | Ícone |
| `permission`   | string, null  | Permissão (interpretação depende do modo) |
| `order`        | int           | Ordenação |
| `target`       | string, null  | `_self`, `_blank`, etc. |
| `is_active`    | bool          | Ativo/inativo |
| `is_separator` | bool          | Item separador (linha) |
| `badge`        | string, null  | Badge (ex.: `novo` ou um contador) |
| `params`       | json, null    | Parâmetros de rota ou metadados |
| `timestamps`   | —             | `created_at` / `updated_at` |
| `deleted_at`   | —             | Soft delete |

---

## Uso

### Ler a árvore completa

Devolve **todos** os itens ativos, já aninhados, servidos da cache (sem joins):

```php
use Gsebastiao\DynamicMenu\Facades\Menu;

$tree = Menu::tree();
// Collection de arrays; cada nó tem uma chave 'children' (array)
```

### Filtrar pela permissão do utilizador

Devolve apenas os itens que o utilizador pode ver, de acordo com o modo configurado. Um pai é mantido se tiver filhos visíveis (evita "buracos" na navegação):

```php
// Utilizador autenticado
$menu = Menu::forUser();

// Um utilizador específico
$menu = Menu::forUser($user);

// Ou passando as permissões diretamente
$menu = Menu::forUser(null, ['user.create', 'admin.access']);
```

No modo `none`, `forUser()` devolve simplesmente a árvore completa.

### Renderizar em Blade

Cada nó é um array. Exemplo recursivo simples:

```blade
{{-- resources/views/partials/menu.blade.php --}}
<ul>
    @foreach ($items as $item)
        @if ($item['is_separator'])
            <li class="separator"><hr></li>
        @else
            <li>
                <a href="{{ $item['route'] ? (\Illuminate\Support\Str::startsWith($item['route'], ['http', '/']) ? $item['route'] : route($item['route'], $item['params'] ?? [])) : '#' }}"
                   target="{{ $item['target'] ?? '_self' }}">
                    @if ($item['icon']) <i class="icon-{{ $item['icon'] }}"></i> @endif
                    {{ $item['label'] }}
                    @if ($item['badge']) <span class="badge">{{ $item['badge'] }}</span> @endif
                </a>

                @if (!empty($item['children']))
                    @include('partials.menu', ['items' => $item['children']])
                @endif
            </li>
        @endif
    @endforeach
</ul>
```

```blade
{{-- No layout --}}
@include('partials.menu', ['items' => \Gsebastiao\DynamicMenu\Facades\Menu::forUser()])
```

### Criar itens

```php
use Gsebastiao\DynamicMenu\Models\MenuItem;

$admin = MenuItem::create([
    'name'       => 'admin',
    'label'      => 'Administração',
    'icon'       => 'shield',
    'order'      => 1,
    'permission' => 'admin.access', // no modo 'id' seria o id, ex.: '42'
]);

MenuItem::create([
    'parent_id'  => $admin->id,
    'name'       => 'admin.users',
    'label'      => 'Utilizadores',
    'route'      => 'admin.users.index',
    'order'      => 1,
    'permission' => 'users.view',
    'badge'      => 'novo',
    'params'     => ['tab' => 'active'],
]);
```

A cache é invalidada automaticamente sempre que gravas, apagas ou restauras um item.

---

## Modos de permissão em detalhe

### Modo `none` (default)

Nada a configurar. O campo `permission` é ignorado, `Menu::tree()` e `Menu::forUser()` devolvem tudo, e o middleware deixa passar sempre. Ideal para projetos simples.

```php
// config/dynamic-menu.php
'permission_mode' => 'none',
```

### Modo `string`

Guardas a permissão como texto. Por omissão, o pacote obtém as permissões do utilizador via `$user->getAllPermissions()` (compatível com vários setups, incluindo Spatie) e compara as strings. Podes personalizar com o callback `user_permissions`:

```php
'permission_mode' => 'string',

'user_permissions' => function ($user) {
    // devolve um array/Collection de strings
    return $user?->permissions()->pluck('name')->all() ?? [];
},
```

```php
$menu = Menu::forUser($user); // já filtrado pelas permissões do $user
```

### Modo `id`

Guardas o **id** da permissão (como texto) e apontas o resolver para a tua tabela. O pacote resolve o id contra ela quando precisa do rótulo legível, e compara ids na filtragem:

```php
'permission_mode' => 'id',

'resolver' => [
    'table'  => 'permissions', // a TUA tabela
    'key'    => 'id',
    'column' => 'name',
],

'user_permissions' => function ($user) {
    // devolve os IDS das permissões do utilizador
    return $user?->permissions()->pluck('id')->all() ?? [];
},
```

Obter o rótulo legível de um item:

```php
$item->resolvedPermissionLabel(); // ex.: 'gerir.tudo' (lido da tua tabela)
```

---

## Middleware

O pacote regista o alias `menu.permission`. Protege rotas exigindo uma permissão:

```php
Route::get('/admin', [AdminController::class, 'index'])
    ->middleware('menu.permission:admin.access');
```

- No modo `none`, o middleware é um **no-op** (deixa passar sempre) — o mesmo código funciona em projetos com e sem permissões.
- Nos modos `string`/`id`, devolve **403** se o utilizador não tiver a permissão indicada.

---

## Cache

A árvore é lida da cache, sem joins. Configura o store em `config/dynamic-menu.php`:

```php
'cache' => [
    'enabled' => true,
    'store'   => 'redis', // ou null para o default, 'file', etc.
    'ttl'     => 3600,    // segundos; null = forever
],
```

A cache é invalidada automaticamente em `saved` / `deleted` / `restored` do model. Para gerir manualmente:

```bash
# Reconstruir a cache
php artisan dynamic-menu:cache

# Apenas limpar
php artisan dynamic-menu:cache --flush
```

Ou por código:

```php
Menu::rebuildCache();
Menu::flushCache();
```

---

## Seeder

O pacote inclui um seeder de exemplo com uma hierarquia de várias profundidades. Publica-o e adapta:

```bash
php artisan vendor:publish --tag=dynamic-menu-seeders
```

Depois corre:

```php
// database/seeders/DatabaseSeeder.php
$this->call(\Database\Seeders\MenuItemsSeeder::class);
```

```bash
php artisan db:seed --class="Database\\Seeders\\MenuItemsSeeder"
```

Ou usa diretamente o seeder do pacote sem publicar:

```bash
php artisan db:seed --class="Gsebastiao\\DynamicMenu\\Database\\Seeders\\MenuItemsSeeder"
```

---

## Testes

O pacote usa [Pest](https://pestphp.com/) com [Orchestra Testbench](https://github.com/orchestral/testbench).

```bash
composer install
vendor/bin/pest
```

A suite cobre: montagem da árvore em N níveis, ordenação, cache e sua invalidação, filtragem por permissão nos três modos, resolução via tabela no modo `id`, o middleware e o comando artisan.

---

## Licença

MIT. Ver [LICENSE.md](LICENSE.md).
