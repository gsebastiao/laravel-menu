# Changelog

Todas as alterações relevantes deste pacote ficam registadas neste ficheiro.

O formato segue o [Keep a Changelog](https://keepachangelog.com/pt-BR/1.1.0/) e o projeto usa [Versionamento Semântico](https://semver.org/lang/pt-BR/): uma versão **MAJOR** (3.0.0) pode exigir mudanças no teu código, uma **MINOR** (2.1.0) acrescenta funcionalidades sem as exigir e uma **PATCH** (2.1.2) só corrige bugs.

## [Não lançado]

## [2.1.2] - 2026-09-22

Versão de correções. Não há passos de atualização: basta `composer update gsebastiao/laravel-menu`.

> A **2.1.1** foi etiquetada por engano no mesmo commit da 2.1.0, por isso tem exatamente o mesmo código. Quem está na 2.1.0 ou na 2.1.1 tem as mesmas correções por fazer: atualiza para a 2.1.2.

### Corrigido

- Um item cuja rota exige um parâmetro que não está em `params` (ex.: `users.edit` sem `['user' => 5]`) continuava a aparecer sem link, como está documentado, mas escrevia a exceção no log **em cada pedido**. Um único item mal configurado enchia o log da aplicação.
- `$item->resolvedPermissionLabel()` dava `Undefined array key` quando o `config/menu.php` publicado não tinha todas as chaves de `resolver`. Agora usa os mesmos valores por omissão do ficheiro de configuração (`auth_permissions`, `id`, `name`), tal como o resto do pacote já fazia.
- O componente Blade rebentava com `Undefined array key "label"` quando lhe passavam em `:items` itens próprios sem `label`. Agora desenha-os com o texto vazio.
- `php artisan laravel-menu:cache` dizia "Cache de menus reconstruída" mesmo com `MENU_CACHE_ENABLED=false`, quando na verdade nada tinha sido guardado. Passa a avisar que a cache está desligada.
- Componente Blade: a página atual não era identificada para leitores de ecrã. O link da página atual passa a levar `aria-current="page"` — só ele, e não os itens-pai, que continuam apenas com a classe `is-active`.
- `:items` aceita agora qualquer iterable (antes, um generator rebentava com `count()`).
- `composer.json`: `illuminate/cache` e `illuminate/contracts` passam a estar declarados; já eram usados (`Cache::store()` e o contrato `Repository`) sem constarem do `require`.

### Adicionado

- `Menu::cacheEnabled()`: diz se a cache está ligada, sem repetir a leitura da configuração. É o que o comando usa para dar o aviso acima.

### Desenvolvimento

- Testes: 77 → 82, com um teste por cada correção.
- O teste do seeder comparava caminhos com `/`, por isso falhava no Windows; passa a normalizar as barras.
- O teste de integração com o `gsebastiao/laravel-auditable` procurava a migration pelo nome que ela tinha na 1.x e falhava com a 2.x, que o pacote já aceita. Passa a encontrá-la em qualquer das duas.
- CI: o `laravel/framework` da matriz passa a ser instalado como dependência de desenvolvimento.

## [2.1.1] - 2026-09-19

Etiquetada por engano no mesmo commit da 2.1.0: **o código é exatamente o mesmo**. Não traz nada de novo nem corrige nada. Se estás nesta versão, atualiza para a 2.1.2.

## [2.1.0] - 2026-09-19

Versão focada em tornar o pacote mais fácil de usar e mais fiável: um componente Blade para mostrar o menu com uma linha, ajudas para links e para o item ativo, auditoria opcional e a correção de vários bugs — alguns faziam o menu mostrar itens que não devia. Atualizar a partir da 2.0 não exige mudanças no teu código.

### Atenção ao atualizar

- **Se publicaste o seeder na 2.0**, publica-o de novo: `php artisan vendor:publish --tag=laravel-menu-seeders --force`. A cópia antiga tinha o namespace errado e não corria.
- **Middleware com várias permissões:** `menu.permission:a,b` passa a deixar entrar quem tiver `a` **ou** `b`. Antes, só `a` era verificada.
- **Menus filtrados:** grupos sem link que ficam sem filhos visíveis e separadores soltos deixam de aparecer.
- **`MENU_PERMISSION_MODE` com um valor desconhecido** (ex.: `strings`) passa a dar um erro claro. Antes funcionava como `string` sem avisar.

### Adicionado

- Componente Blade `<x-laravel-menu::menu />`, que desenha o menu do utilizador autenticado com submenus, ícones, badges, separadores e o item ativo destacado. Aceita outros itens (`:items`) e atributos para a `<ul>`, e o HTML pode ser personalizado com `php artisan vendor:publish --tag=laravel-menu-views`.
- `Menu::url($item)`: devolve o link de um item a partir de um nome de rota (com `params`), de um caminho (`/sobre`), de um endereço completo ou de uma âncora. Devolve `null` em vez de rebentar a página quando a rota não existe ou faltam parâmetros, e nunca gera links `javascript:`, `data:`, `vbscript:` ou `file:`.
- `Menu::isActive($item)`: indica se o item, ou algum descendente, é a página atual. Um item `users.index` também fica ativo em `users.create`, `users.edit`, etc.
- `Menu::hasPermission($permissoes, $user = null)`: verifica uma ou várias permissões (basta ter uma), com a mesma lógica do middleware. Útil em controllers e views.
- `Menu::resolvePermissionsUsing(fn ($user) => ...)`: define em código como obter as permissões do utilizador, sem usar closures no ficheiro de config.
- `config('menu.user_permissions')` aceita agora uma classe invocável, `[Classe::class, 'metodo']` ou `'Classe@metodo'`, que são compatíveis com `php artisan config:cache`.
- As permissões devolvidas pelo resolver podem ser Collections, models, arrays associativos, objetos ou enums.
- Integração opcional com o [gsebastiao/laravel-auditable](https://github.com/gsebastiao/laravel-auditable), ativada com `MENU_AUDITING_ENABLED=true`:
  - regista automaticamente cada create, update, delete e restore feito com o `MenuItem`, guardando o nome do item pai em vez do `parent_id`;
  - acrescenta a relação `$item->audits` com o histórico de cada item;
  - se for ativada sem o pacote instalado, a aplicação pára no arranque com uma mensagem a explicar como o instalar;
  - o pacote de auditoria continua opcional: não é instalado com o laravel-menu.
- O seeder de exemplo pode ser corrido várias vezes: os itens são atualizados (pelo `name`) em vez de duplicados.
- Comando `composer test` para correr os testes.

### Corrigido

- Um item acabado de criar com `create()` ficava com `is_active`, `is_separator` e `order` a `null` em memória, e `isVisibleTo()` devolvia `false` até o item ser lido de novo da base de dados.
- Um separador dentro de um grupo mantinha o grupo visível para quem não tinha acesso a nenhum dos itens dele (ex.: "Administração" aparecia a qualquer utilizador).
- Grupos sem link apareciam vazios quando todos os filhos ficavam escondidos, e separadores podiam aparecer no início, no fim ou repetidos.
- O middleware `menu.permission:a,b` ignorava todas as permissões a partir da segunda.
- Um resolver de permissões que devolvesse arrays (ex.: `$user->permissions->toArray()`) causava o erro "Array to string conversion".
- Com `MENU_CACHE_TTL=` presente mas vazio no `.env`, a cache nunca funcionava: a árvore era guardada com um prazo de 0 segundos e apagada logo a seguir. Vazio ou `0` significa agora "sem prazo".
- Com a cache desligada, alterar itens não limpava a árvore guardada antes. Ao voltar a ligar a cache, o menu mostrava dados desatualizados.
- O seeder era publicado com o namespace do pacote, por isso `php artisan db:seed --class=MenuItemsSeeder` falhava depois de o publicar.
- A relação `children()` não desempatava itens com o mesmo `order`, e podia mostrá-los por uma ordem diferente da árvore.
- `MenuItem::getTable()` ignorava a propriedade `$table` definida numa subclasse.
- Documentação: o link do badge de licença estava partido, o exemplo do modo `id` usava a tabela `permissions` em vez do padrão `auth_permissions`, e era recomendada uma closure no ficheiro de config, o que faz o `php artisan config:cache` falhar.

### Alterado

- README reescrito para quem está a começar: guia "Começar em 5 minutos", tabelas com os campos dos itens e as opções de configuração, referência rápida, resolução de problemas e guias de atualização.
- Comentários do `config/menu.php` reescritos em linguagem mais simples.
- A árvore é montada a partir dos itens de topo: filhos de itens inativos ou apagados nunca chegam a ser processados.
- O `MenuManager` é registado como singleton, para que um resolver definido com `resolvePermissionsUsing()` valha durante todo o pedido.
- Dependências `illuminate/http`, `illuminate/routing` e `illuminate/view` declaradas no `composer.json` (as duas primeiras já eram usadas sem estarem declaradas).
- Desenvolvimento: suporte a Pest 4 e 5 e ao laravel-auditable 2.x; `phpunit.xml` passa a `phpunit.xml.dist`; o CI corre os testes com e sem o pacote de auditoria.

## [2.0.0] - 2026-08-17

O pacote mudou de nome, de `gsebastiao/laravel-dynamic-menu` para `gsebastiao/laravel-menu`. O funcionamento é o mesmo da 1.0.0, mas os nomes abaixo mudaram e é preciso atualizá-los no teu projeto. O README tem um guia passo a passo.

### Alterado (incompatível com a 1.x)

- Pacote Composer: `gsebastiao/laravel-dynamic-menu` → `gsebastiao/laravel-menu`.
- Namespace: `Gsebastiao\DynamicMenu` → `Gsebastiao\LaravelMenu`.
- Service provider: `DynamicMenuServiceProvider` → `MenuServiceProvider`.
- Ficheiro de configuração: `config/dynamic-menu.php` → `config/menu.php` (e `config('dynamic-menu.…')` → `config('menu.…')`).
- Variáveis de ambiente: prefixo `DYNAMIC_MENU_` → `MENU_`.
- Tabela padrão do resolver (modo `id`): `permissions` → `auth_permissions`.
- Comando: `php artisan dynamic-menu:cache` → `php artisan laravel-menu:cache`.
- Tags de publicação: `dynamic-menu-config`, `dynamic-menu-migrations` e `dynamic-menu-seeders` → `laravel-menu-config`, `laravel-menu-migrations` e `laravel-menu-seeders`.
- Alias no container: `dynamic-menu` → `laravel-menu`.
- Chave da cache: `dynamic_menu` → `laravel_menu`.

A tabela `menu_items` e o nome da migration não mudaram: os dados mantêm-se e a migration não volta a correr.

## [1.0.0] - 2026-07-11

Primeira versão, publicada como `gsebastiao/laravel-dynamic-menu`.

### Adicionado

- Sistema de menus dinâmicos e hierárquicos com N níveis.
- Model `MenuItem` com soft deletes, casts, scopes (`active`, `roots`, `ordered`) e relações de árvore (`parent`, `children`, `childrenRecursive`).
- Campo único `permission` (nullable) com três modos configuráveis: `none`, `string` e `id`.
- Resolver configurável para o modo `id`, apontando para qualquer tabela do projeto (100% independente de pacotes de permissões).
- `MenuManager` com montagem de árvore sem joins e cache (file/Redis/qualquer store), com invalidação automática em `saved`/`deleted`/`restored`.
- Facade `Menu` (`tree`, `forUser`, `rebuildCache`, `flushCache`).
- Middleware `menu.permission` para proteger rotas.
- Comando `php artisan dynamic-menu:cache` (com `--flush`).
- Seeder de exemplo `MenuItemsSeeder`.
- Testes automatizados (Pest) e CI para Laravel 11/12/13 em PHP 8.2–8.4.

[Não lançado]: https://github.com/gsebastiao/laravel-menu/compare/v2.1.2...HEAD
[2.1.2]: https://github.com/gsebastiao/laravel-menu/compare/v2.1.1...v2.1.2
[2.1.1]: https://github.com/gsebastiao/laravel-menu/compare/v2.1.0...v2.1.1
[2.1.0]: https://github.com/gsebastiao/laravel-menu/compare/v2.0.0...v2.1.0
[2.0.0]: https://github.com/gsebastiao/laravel-menu/compare/v1.0.0...v2.0.0
[1.0.0]: https://github.com/gsebastiao/laravel-menu/releases/tag/v1.0.0
