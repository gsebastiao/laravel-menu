# Laravel Menu

[![License](https://img.shields.io/packagist/l/gsebastiao/laravel-menu.svg)](LICENSE.md)
[![PHP Version](https://img.shields.io/packagist/php-v/gsebastiao/laravel-menu.svg)](composer.json)
[![Laravel Framework](https://img.shields.io/packagist/dependency-v/gsebastiao/laravel-menu/illuminate/support.svg)](composer.json)
[![Latest Version](https://img.shields.io/packagist/v/gsebastiao/laravel-menu.svg)](https://packagist.org/packages/gsebastiao/laravel-menu)
[![Testes](https://github.com/gsebastiao/laravel-menu/actions/workflows/tests.yml/badge.svg)](https://github.com/gsebastiao/laravel-menu/actions/workflows/tests.yml)

Menus para Laravel guardados na base de dados, com os níveis que precisares (menu › submenu › sub-submenu…), que mostram a cada utilizador só o que ele pode ver.

- **Funciona sem nenhum pacote de permissões** — ou com o que já usas (Spatie, tabelas próprias, etc.).
- **Rápido:** a árvore fica em cache e a cache limpa-se sozinha quando mudas um item.
- **Pronto a mostrar:** um componente Blade desenha o menu com uma linha.

---

## Índice

1. [Requisitos](#requisitos)
2. [Começar em 5 minutos](#começar-em-5-minutos)
3. [Criar itens de menu](#criar-itens-de-menu)
4. [Mostrar o menu](#mostrar-o-menu)
5. [Permissões](#permissões)
6. [Proteger rotas (middleware)](#proteger-rotas-middleware)
7. [Cache](#cache)
8. [Auditoria (opcional)](#auditoria-opcional)
9. [Configuração](#configuração)
10. [Referência rápida](#referência-rápida)
11. [Resolução de problemas](#resolução-de-problemas)
12. [Atualizar de uma versão anterior](#atualizar-de-uma-versão-anterior)
13. [Testes](#testes)

---

## Requisitos

| Laravel      | PHP   |
|--------------|-------|
| 11.x e 12.x  | 8.2 ou mais recente |
| 13.x         | 8.3 ou mais recente |

---

## Começar em 5 minutos

**1. Instala o pacote**

```bash
composer require gsebastiao/laravel-menu
```

**2. Cria a tabela `menu_items`**

```bash
php artisan migrate
```

**3. Cria alguns itens.** Para experimentar, usa o menu de exemplo que vem com o pacote:

```bash
php artisan vendor:publish --tag=laravel-menu-seeders
```

```bash
php artisan db:seed --class=MenuItemsSeeder
```

> O primeiro comando copia o exemplo para `database/seeders/MenuItemsSeeder.php`, onde o podes editar. Podes correr o seeder as vezes que quiseres: os itens são atualizados, não duplicados.

**4. Mostra o menu.** No teu layout (ex.: `resources/views/layouts/app.blade.php`):

```blade
<nav>
    <x-laravel-menu::menu />
</nav>
```

Pronto: tens um menu a funcionar. Os itens do exemplo que apontam para rotas que ainda não existem no teu projeto aparecem sem link, sem erros.

> **Queres mudar alguma opção (permissões, cache, nome da tabela...)?** Não
> precisas de publicar o config: basta acrescentar variáveis `MENU_*` ao teu
> `.env`. A lista completa, com o padrão de cada uma, está em
> [Configuração](#configuração). Se fores mudar o nome da tabela
> (`MENU_TABLE`), faz isso **antes** do `migrate`.

**5. (Opcional) Dá-lhe um estilo simples:**

```css
.menu, .menu-submenu { list-style: none; margin: 0; padding: 0; }
.menu-submenu { padding-left: 1rem; }
.menu-link { display: flex; align-items: center; gap: .5rem; padding: .4rem .75rem; color: inherit; text-decoration: none; border-radius: .375rem; }
a.menu-link[href]:hover { background: #f1f5f9; }
.menu-item.is-active > .menu-link { font-weight: 600; background: #e2e8f0; }
.menu-badge { margin-left: auto; font-size: .75rem; padding: 0 .45rem; border-radius: 999px; background: #2563eb; color: #fff; }
.menu-separator { border-top: 1px solid #e2e8f0; margin: .4rem 0; }
```

---

## Criar itens de menu

Cada item é uma linha da tabela `menu_items`. Crias, editas e apagas itens como qualquer model do Laravel:

```php
use Gsebastiao\LaravelMenu\Models\MenuItem;

// Um item de topo que leva a uma rota
MenuItem::create([
    'name'  => 'dashboard',
    'label' => 'Dashboard',
    'route' => 'dashboard',
    'order' => 1,
]);

// Um grupo (sem link) com dois filhos
$admin = MenuItem::create([
    'name'  => 'admin',
    'label' => 'Administração',
    'order' => 2,
]);

MenuItem::create([
    'parent_id' => $admin->id,
    'name'      => 'admin.users',
    'label'     => 'Utilizadores',
    'route'     => 'users.index',
    'badge'     => 'novo',
]);

MenuItem::create([
    'parent_id'    => $admin->id,
    'name'         => 'admin.separador',
    'label'        => '—',
    'is_separator' => true,
]);
```

Podes pôr este código num seeder (como o de exemplo) ou experimentá-lo no `php artisan tinker`. A cache do menu é limpa automaticamente sempre que crias, editas, apagas ou restauras um item assim.

### Os campos de um item

| Campo          | Obrigatório | Para que serve | Exemplo |
|----------------|-------------|----------------|---------|
| `name`         | sim         | Identificador interno do item. Usa um nome diferente para cada item. | `'admin.users'` |
| `label`        | sim         | O texto que aparece no menu. | `'Utilizadores'` |
| `parent_id`    | não         | O `id` do item pai. Vazio = item de topo. | `$admin->id` |
| `route`        | não         | Para onde o item leva (ver tabela abaixo). Vazio = item sem link (ex.: um grupo). | `'users.index'` |
| `params`       | não         | Parâmetros da rota. | `['user' => 5]` |
| `order`        | não (`0`)   | Posição entre os irmãos: os números menores aparecem primeiro. | `1` |
| `icon`         | não         | Classes CSS do ícone (do pacote de ícones que usas). | `'bi bi-people'` |
| `badge`        | não         | Texto pequeno ao lado do nome. | `'novo'` |
| `description`  | não         | Texto de ajuda (aparece ao passar o rato). | `'Gerir contas'` |
| `target`       | não         | `'_blank'` abre o link num novo separador. | `'_blank'` |
| `permission`   | não         | Permissão necessária para ver o item (ver [Permissões](#permissões)). | `'users.view'` |
| `is_active`    | não (`true`)| `false` esconde o item **e todos os filhos** dele. | `false` |
| `is_separator` | não (`false`)| Mostra uma linha divisória em vez de um link. | `true` |

### O que pode ir no campo `route`

| Escreves                       | O item leva a…                                          |
|--------------------------------|---------------------------------------------------------|
| `'users.index'`                | a rota com esse **nome** (`route('users.index', $params)`) |
| `'/sobre'`                     | um **caminho** da tua aplicação (começa por `/`)          |
| `'https://exemplo.com'`        | um **endereço externo** (também `mailto:` e `tel:`)       |
| `'#contactos'`                 | uma **âncora** na página                                |

Se o nome de rota não existir (ou faltar um parâmetro obrigatório), o item aparece **sem link** em vez de dar erro na página. Por segurança, endereços `javascript:` e `data:` nunca viram link.

> **Dica:** num painel de administração, gere os itens com um CRUD normal (controller + formulário) sobre o model `MenuItem`.

---

## Mostrar o menu

### Com o componente pronto

```blade
<x-laravel-menu::menu />
```

Mostra o menu do utilizador autenticado, já filtrado pelas permissões, com os submenus aninhados. Também aceita outros itens e atributos para a `<ul>` principal:

```blade
<x-laravel-menu::menu class="sidebar" id="menu-principal" />
<x-laravel-menu::menu :items="$outrosItens" />
```

O HTML gerado usa estas classes, para poderes dar-lhe o estilo que quiseres:

| Classe            | Onde aparece |
|-------------------|--------------|
| `menu`            | cada `<ul>` do menu |
| `menu-submenu`    | cada `<ul>` de um submenu |
| `menu-item`       | cada `<li>` com um item |
| `is-active`       | o `<li>` da página atual **e dos seus pais** (para abrires o submenu certo) |
| `has-children`    | o `<li>` de um item com submenu |
| `menu-link`       | o `<a>` de cada item (sem `href` quando o item não tem link) |
| `menu-icon`, `menu-label`, `menu-badge` | o ícone, o texto e o badge |
| `menu-separator`  | o `<li>` de um separador |

### Mudar o HTML do componente

Publica a view e edita a cópia (ex.: para usares as classes do Bootstrap ou do Tailwind):

```bash
php artisan vendor:publish --tag=laravel-menu-views
```

A cópia fica em `resources/views/vendor/laravel-menu/components/menu.blade.php` e passa a ser usada no lugar da original.

### Montar o teu próprio HTML

Se preferires escrever tudo à mão, estas são as peças que o componente usa:

```blade
@foreach (Menu::forUser() as $item)
    <a href="{{ Menu::url($item) ?? '#' }}" @class(['active' => Menu::isActive($item)])>
        {{ $item['label'] }}
    </a>

    {{-- Os filhos estão em $item['children'] (com a mesma estrutura) --}}
@endforeach
```

- `Menu::forUser()` devolve os itens de topo que o utilizador pode ver. Cada item é um **array** com todos os campos da tabela e uma chave `children` com os filhos.
- `Menu::url($item)` devolve o link do item, ou `null` se não tiver.
- `Menu::isActive($item)` diz se o item (ou um dos filhos) é a página atual. Um item com a rota `users.index` também fica ativo em `users.create`, `users.edit`, etc.

---

## Permissões

Por omissão **não há permissões**: todos os itens aparecem a toda a gente. Para esconder itens, são dois passos.

### Passo 1: escolhe o modo

No `.env`:

```env
MENU_PERMISSION_MODE=string
```

| Modo     | O que guardas no campo `permission` de cada item |
|----------|--------------------------------------------------|
| `none`   | Nada — as permissões são ignoradas (é o padrão). |
| `string` | O **nome** da permissão, ex.: `users.view`.      |
| `id`     | O **id** da permissão numa tabela tua, ex.: `42` (ver [Modo id](#modo-id)). |

### Passo 2: diz ao pacote quais são as permissões do utilizador

**Usas o [spatie/laravel-permission](https://github.com/spatie/laravel-permission)?** Não precisas de fazer nada: o pacote usa `$user->getAllPermissions()` automaticamente.

**Tens outra lógica?** Define-a no `boot()` do `app/Providers/AppServiceProvider.php`:

```php
use Gsebastiao\LaravelMenu\Facades\Menu;

public function boot(): void
{
    Menu::resolvePermissionsUsing(function ($user) {
        // Devolve os nomes das permissões (no modo 'id', os ids).
        return $user?->permissions()->pluck('name') ?? [];
    });
}
```

O `$user` é o utilizador autenticado (ou `null` para visitantes). Podes devolver um array ou uma Collection de textos, models, arrays ou enums (de um model ou array é lida a coluna `name`; no modo `id`, a coluna `id`).

<details>
<summary>Alternativa: indicar uma classe no <code>config/menu.php</code></summary>

```php
// config/menu.php
'user_permissions' => \App\Support\MenuPermissions::class,
```

```php
// app/Support/MenuPermissions.php
namespace App\Support;

class MenuPermissions
{
    public function __invoke($user): iterable
    {
        return $user?->permissions()->pluck('name') ?? [];
    }
}
```

**Não escrevas uma função (closure) diretamente no ficheiro de config:** funciona, mas o `php artisan config:cache` deixa de funcionar.

</details>

### Quem vê o quê

| Situação | Resultado |
| ---------- | ----------- |
| Item **sem** `permission` | Aparece a todos. |
| Item **com** `permission` | Aparece só a quem tiver essa permissão. |
| Item com `is_active = false` | Não aparece a ninguém, nem os filhos. |
| Item cujo pai o utilizador não pode ver | Aparece na mesma, e o pai também (para não ficar um "buraco" na navegação). |
| Grupo **sem link** cujos filhos ficaram todos escondidos | Desaparece (não fica um grupo vazio). |
| Separador | Não conta como filho visível; separadores soltos (no início, no fim ou repetidos) são removidos. |

### Verificar permissões no teu código

```php
Menu::hasPermission('users.create');                 // true / false
Menu::hasPermission(['users.create', 'users.edit']); // true se tiver PELO MENOS UMA
```

```blade
@if (Menu::hasPermission('users.create'))
    <a href="{{ route('users.create') }}">Novo utilizador</a>
@endif
```

No modo `none`, `hasPermission()` devolve sempre `true`.

### Modo id

Usa este modo se o campo `permission` guardar **ids** de uma tabela tua. Indica a tabela no `.env`:

```env
MENU_PERMISSION_MODE=id
MENU_RESOLVER_TABLE=permissions
MENU_RESOLVER_KEY=id
MENU_RESOLVER_COLUMN=name
```

- O resolver de permissões (passo 2) deve devolver os **ids** das permissões do utilizador.
- `$item->resolvedPermissionLabel()` devolve o nome legível da permissão do item, lido dessa tabela (útil num painel de administração).

---

## Proteger rotas (middleware)

O pacote regista o middleware `menu.permission`:

```php
Route::get('/utilizadores', [UserController::class, 'index'])
    ->middleware(['auth', 'menu.permission:users.view']);

// Com várias permissões, basta ter UMA delas:
Route::get('/relatorios', [ReportController::class, 'index'])
    ->middleware(['auth', 'menu.permission:reports.view,reports.export']);
```

- Quem não tiver a permissão recebe um erro **403**.
- No modo `none`, o middleware deixa passar sempre — o mesmo código serve para projetos com e sem permissões.
- Junta-o ao middleware `auth`, para que um visitante seja enviado para o login em vez de receber um 403.

---

## Cache

A árvore do menu é guardada em cache e **limpa-se sozinha** quando um item é criado, editado, apagado ou restaurado através do model (`create()`, `update()`, `save()`, `delete()`, `restore()`).

A cache **não** é limpa sozinha quando alteras os itens por outras vias:

- updates em massa: `MenuItem::where(...)->update([...])`;
- `saveQuietly()`, `updateQuietly()` ou `MenuItem::withoutEvents(...)`;
- alterações diretas na base de dados (SQL, `DB::table(...)`, outra aplicação).

Nesses casos, limpa-a à mão:

```bash
php artisan laravel-menu:cache          # reconstrói a cache
```

```bash
php artisan laravel-menu:cache --flush  # só limpa
```

```php
Menu::flushCache();   // limpa
Menu::rebuildCache(); // limpa e volta a guardar
```

As opções da cache ficam no `.env` (ver a tabela em [Configuração](#configuração)):

```env
MENU_CACHE_ENABLED=true
MENU_CACHE_STORE=redis
MENU_CACHE_TTL=3600
```

---

## Auditoria (opcional)

Com o pacote [gsebastiao/laravel-auditable](https://github.com/gsebastiao/laravel-auditable), fica registado **quem** criou, editou, apagou ou restaurou cada item de menu, **quando** e **o que mudou**. O laravel-menu não depende desse pacote: só o instalas se quiseres.

**1. Instala o pacote de auditoria:**

```bash
composer require gsebastiao/laravel-auditable
```

```bash
php artisan migrate
```

O pacote de auditoria traz a própria migration e o `migrate` a executa: não precisas de publicar nada. (Só publica o config ou a migration se os quiseres editar — vê o README do laravel-auditable.)

**2. Liga a auditoria no `.env`:**

```env
MENU_AUDITING_ENABLED=true
```

Não tens de mudar mais nada: `MenuItem::create()`, `$item->update()`, `$item->delete()` e `$item->restore()` passam a ficar registados. Em vez do `parent_id`, a auditoria guarda o nome do item pai.

```php
$item->audits; // histórico deste item
```

> Se ligares a auditoria **sem** o pacote instalado, a aplicação pára no arranque com uma mensagem a explicar como instalá-lo. Assim nunca ficas a pensar que a auditoria está a gravar quando não está.

<details>
<summary>Mudar o que é gravado</summary>

Cria um model teu que estenda o `MenuItem` e sobrescreve `getAuditOptions()`:

```php
namespace App\Models;

use Gsebastiao\Auditable\Support\AuditOptions;
use Gsebastiao\LaravelMenu\Models\MenuItem as BaseMenuItem;

class MenuItem extends BaseMenuItem
{
    public function getAuditOptions(): AuditOptions
    {
        return parent::getAuditOptions()->except(['badge', 'order']);
    }
}
```

Indica-o no `config/menu.php` (`'model' => \App\Models\MenuItem::class`) e usa-o no teu código. Se quiseres os métodos extra do laravel-auditable (`auditAction()`, `operations()`…), acrescenta `use \Gsebastiao\Auditable\Concerns\Auditable;` a essa classe — o laravel-menu deteta-o e não grava nada em duplicado.

</details>

---

## Configuração

Tudo funciona sem configurar nada, e **não precisas de publicar o ficheiro de configuração**. Para mudar alguma coisa, usa o `.env`:

| Variável | Padrão | Para que serve |
| ---------- | -------- | ---------------- |
| `MENU_TABLE` | `menu_items` | Nome da tabela (muda **antes** do `migrate`) |
| `MENU_MODEL` | `Gsebastiao\LaravelMenu\Models\MenuItem` | Model dos itens (uma classe tua que estenda o do pacote) |
| `MENU_PERMISSION_MODE` | `none` | `none`, `string` ou `id` ([Permissões](#permissões)) |
| `MENU_RESOLVER_TABLE` | `auth_permissions` | Tabela de permissões (só no modo `id`) |
| `MENU_RESOLVER_KEY` | `id` | Coluna comparada com o campo `permission` (só no modo `id`) |
| `MENU_RESOLVER_COLUMN` | `name` | Coluna com o nome legível da permissão |
| `MENU_USER_PERMISSION` | (não definir) | Classe com `__invoke($user)` que devolve as permissões do utilizador |
| `MENU_CACHE_ENABLED` | `true` | Liga/desliga a cache |
| `MENU_CACHE_STORE` | (não definir) | Cache a usar (sem valor = a padrão da aplicação) |
| `MENU_CACHE_TTL` | (não definir) | Validade da cache em segundos (sem valor ou 0 = sem prazo) |
| `MENU_CACHE_KEY` | `laravel_menu` | Prefixo da chave na cache |
| `MENU_AUDITING_ENABLED` | `false` | Liga a [auditoria](#auditoria-opcional) |

> Não deixes uma variável em branco (`MENU_CACHE_STORE=`): o Laravel lê isso
> como texto vazio. Para voltar ao padrão, apaga a linha. Com
> `php artisan config:cache`, corre-o de novo depois de mudar o `.env`.

Se preferires editar o ficheiro, publica a configuração:

```bash
php artisan vendor:publish --tag=laravel-menu-config
```

Tudo o que podes publicar:

| Comando | Cria |
| --------- | ------ |
| `php artisan vendor:publish --tag=laravel-menu-config` | `config/menu.php` |
| `php artisan vendor:publish --tag=laravel-menu-views` | `resources/views/vendor/laravel-menu/components/menu.blade.php` |
| `php artisan vendor:publish --tag=laravel-menu-seeders` | `database/seeders/MenuItemsSeeder.php` |
| `php artisan vendor:publish --tag=laravel-menu-migrations` | a migration, em `database/migrations/` (só se a quiseres alterar) |

---

## Referência rápida

**Facade `Menu`** (`use Gsebastiao\LaravelMenu\Facades\Menu;` — nas views Blade já está disponível):

| Método | O que faz |
| -------- | ----------- |
| `Menu::forUser($user = null)` | Itens que o utilizador pode ver, prontos a mostrar. Sem argumentos usa o utilizador autenticado. |
| `Menu::forUser(null, ['a', 'b'])` | O mesmo, para uma lista de permissões que indicas. |
| `Menu::tree()` | Todos os itens ativos, sem filtrar permissões. |
| `Menu::url($item)` | Link do item, ou `null`. |
| `Menu::isActive($item)` | `true` se o item ou um dos filhos é a página atual. |
| `Menu::hasPermission('a')` | `true` se o utilizador tiver a permissão (ou uma de várias). |
| `Menu::resolvePermissionsUsing(fn ($user) => ...)` | Define como obter as permissões do utilizador. |
| `Menu::resolveUserPermissions($user = null)` | As permissões do utilizador, tal como o pacote as vê (útil para depurar). |
| `Menu::rebuildCache()` / `Menu::flushCache()` | Reconstrói / limpa a cache. |

**Model `MenuItem`:**

| Uso | O que faz |
| ----- | ----------- |
| `$item->parent`, `$item->children` | Pai e filhos diretos (pela ordem do menu). |
| `$item->childrenRecursive` | Todos os descendentes, carregados de uma vez. |
| `MenuItem::active()`, `::roots()`, `::ordered()` | Só ativos / só de topo / pela ordem do menu. |
| `$item->isVisibleTo($permissoes)` | O item é visível com estas permissões? |
| `$item->resolvedPermissionLabel()` | Nome legível da permissão (no modo `id`, lido da tua tabela). |
| `$item->audits` | Histórico de auditoria (requer o [laravel-auditable](#auditoria-opcional)). |

**Comando:** `php artisan laravel-menu:cache` (com `--flush` só limpa).

---

## Resolução de problemas

**O menu aparece vazio.**
Confirma que há itens com `is_active = true`. Se usas permissões, vê o que o pacote recebe com `dd(Menu::resolveUserPermissions())` — se vier vazio, revê o [passo 2 das permissões](#passo-2-diz-ao-pacote-quais-são-as-permissões-do-utilizador).

**Alterei itens e o menu não mudou.**
Provavelmente alteraste-os sem passar pelos eventos do model (update em massa, SQL direto…). Corre `php artisan laravel-menu:cache`. Ver [Cache](#cache).

**Um item aparece sem link.**
O nome de rota no campo `route` não existe (confirma com `php artisan route:list`), falta um parâmetro obrigatório em `params`, ou é um caminho sem `/` no início (usa `/sobre`, não `sobre`).

**O super-administrador não vê todos os itens.**
O pacote compara listas de permissões e não passa pelo `Gate`. Faz o resolver devolver todas as permissões nesse caso:

```php
Menu::resolvePermissionsUsing(function ($user) {
    if ($user?->hasRole('super-admin')) {
        return \Spatie\Permission\Models\Permission::pluck('name');
    }

    return $user?->getAllPermissions() ?? [];
});
```

**`php artisan config:cache` falha com "Your configuration files are not serializable".**
Tens uma closure no `config/menu.php` (normalmente em `user_permissions`). Troca-a por `Menu::resolvePermissionsUsing()` ou por uma classe — ver [Permissões](#passo-2-diz-ao-pacote-quais-são-as-permissões-do-utilizador).

**`Target class [Database\Seeders\MenuItemsSeeder] does not exist`.**
O seeder foi publicado por uma versão anterior a 2.1, que o copiava com o namespace errado. Publica-o de novo: `php artisan vendor:publish --tag=laravel-menu-seeders --force`.

**Erro no arranque: "A auditoria do laravel-menu está ativada … mas o pacote … não está instalado".**
Instala o pacote de auditoria (ver [Auditoria](#auditoria-opcional)) ou define `MENU_AUDITING_ENABLED=false`.

**Erro: "Valor inválido em config('menu.permission_mode')".**
O `MENU_PERMISSION_MODE` tem um valor desconhecido (ex.: `strings`). Usa `none`, `string` ou `id`.

---

## Atualizar de uma versão anterior

### De 2.0 para 2.1

Não há passos obrigatórios. Revê apenas:

- **Se publicaste o seeder na 2.0**, publica-o de novo: `php artisan vendor:publish --tag=laravel-menu-seeders --force` (a cópia antiga tinha o namespace errado).
- **Middleware com várias permissões:** `menu.permission:a,b` passou a aceitar quem tiver `a` **ou** `b` (antes só `a` era verificada).
- **Menus filtrados:** grupos sem link que ficam vazios e separadores soltos deixaram de aparecer.
- **`MENU_PERMISSION_MODE` com um valor desconhecido** passa a dar erro (antes funcionava como `string` sem avisar).

O [CHANGELOG](CHANGELOG.md) tem a lista completa.

### De 1.x (`gsebastiao/laravel-dynamic-menu`) para 2.x

Na 2.0 o pacote mudou de nome. A tabela e os dados não mudam, e a migration não volta a correr.

1. Troca o pacote:

   ```bash
   composer remove gsebastiao/laravel-dynamic-menu
   ```

   ```bash
   composer require gsebastiao/laravel-menu
   ```

2. No teu código, substitui `Gsebastiao\DynamicMenu\` por `Gsebastiao\LaravelMenu\`.
3. Se publicaste a config, renomeia `config/dynamic-menu.php` para `config/menu.php` e troca `config('dynamic-menu.…')` por `config('menu.…')`.
4. No `.env`, troca o prefixo `DYNAMIC_MENU_` por `MENU_` (ex.: `DYNAMIC_MENU_PERMISSION_MODE` → `MENU_PERMISSION_MODE`).
5. **Modo `id` sem config publicada:** a tabela padrão passou de `permissions` para `auth_permissions`. Se usas `permissions`, define `MENU_RESOLVER_TABLE=permissions`.
6. Nos scripts de deploy, troca `php artisan dynamic-menu:cache` por `php artisan laravel-menu:cache`, e as tags `dynamic-menu-*` do `vendor:publish` por `laravel-menu-*`.
7. Corre `php artisan laravel-menu:cache` (a chave da cache mudou de `dynamic_menu` para `laravel_menu`).

---

## Testes

```bash
composer install
composer test
```

A suíte usa [Pest](https://pestphp.com/) com [Orchestra Testbench](https://github.com/orchestral/testbench) e corre no CI com Laravel 11, 12 e 13, em PHP 8.2 a 8.4, com e sem o pacote de auditoria.

---

## Licença

MIT. Ver [LICENSE](LICENSE).
