# Changelog

Todas as alterações relevantes deste pacote são documentadas neste ficheiro.

O formato baseia-se em [Keep a Changelog](https://keepachangelog.com/pt-BR/1.0.0/)
e o projeto segue [Versionamento Semântico](https://semver.org/lang/pt-BR/).

## [1.0.0] - 2026-07-10

### Adicionado
- Sistema de menus dinâmicos e hierárquicos com N níveis.
- Model `MenuItem` com soft deletes, casts, scopes (`active`, `roots`, `ordered`)
  e relações de árvore (`parent`, `children`, `childrenRecursive`).
- Campo único `permission` (nullable) com três modos configuráveis:
  `none`, `string` e `id`.
- Resolver configurável para o modo `id`, apontando para qualquer tabela do
  projeto (100% independente de pacotes de permissões).
- `MenuManager` com montagem de árvore sem joins e cache (file/Redis/qualquer
  store), com invalidação automática em `saved`/`deleted`/`restored`.
- Facade `Menu` (`tree`, `forUser`, `rebuildCache`, `flushCache`).
- Middleware `menu.permission` para proteger rotas.
- Comando `php artisan dynamic-menu:cache` (com `--flush`).
- Seeder de exemplo `MenuItemsSeeder`.
- Testes automatizados (Pest) e CI para Laravel 11/12/13 em PHP 8.2–8.4.
