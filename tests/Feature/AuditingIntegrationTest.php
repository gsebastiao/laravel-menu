<?php

declare(strict_types=1);

use Composer\InstalledVersions;
use Gsebastiao\LaravelMenu\Models\MenuItem;
use Gsebastiao\LaravelMenu\MenuServiceProvider;
use Gsebastiao\LaravelMenu\Support\AuditingSupport;
use Gsebastiao\LaravelMenu\Tests\Fixtures\MenuItemComTraitAuditable;
use Gsebastiao\LaravelMenu\Tests\Fixtures\MenuItemPersonalizado;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

$semPacote = ! AuditingSupport::packageInstalled();

/**
 * Cria a tabela de auditoria a partir da migration (stub) do pacote.
 */
function criarTabelaDeAuditoria(): void
{
    $path = InstalledVersions::getInstallPath(AuditingSupport::PACKAGE_NAME);

    (require $path.'/database/migrations/create_audits_table.php.stub')->up();
}

/**
 * @return Collection<int, object>
 */
function auditorias(): Collection
{
    return DB::table(config('auditable.table'))->orderBy('id')->get()
        ->each(fn (object $linha) => $linha->changes = json_decode($linha->changes, true));
}

/*
|--------------------------------------------------------------------------
| Sem configuração / sem pacote
|--------------------------------------------------------------------------
*/

it('vem desligada por omissão e não interfere com o pacote', function () {
    $item = MenuItem::create(['name' => 'a', 'label' => 'A']);

    expect(config('menu.auditing.enabled'))->toBeFalse()
        ->and(AuditingSupport::shouldAudit())->toBeFalse()
        ->and($item->exists)->toBeTrue();
});

it('falha no arranque, com instruções, se for ativada sem o pacote instalado', function () {
    config()->set('menu.auditing.enabled', true);

    expect(fn () => (new MenuServiceProvider(app()))->boot(app('router')))
        ->toThrow(RuntimeException::class, 'composer require gsebastiao/laravel-auditable');
})->skip(! $semPacote, 'Cenário para quando gsebastiao/laravel-auditable NÃO está instalado.');

it('$item->audits explica que o pacote de auditoria não está instalado', function () {
    $item = MenuItem::create(['name' => 'a', 'label' => 'A']);

    expect(fn () => $item->audits)->toThrow(LogicException::class, AuditingSupport::PACKAGE_NAME);
})->skip(! $semPacote, 'Cenário para quando gsebastiao/laravel-auditable NÃO está instalado.');

/*
|--------------------------------------------------------------------------
| Com o pacote gsebastiao/laravel-auditable instalado
|--------------------------------------------------------------------------
*/

describe('com gsebastiao/laravel-auditable instalado', function () use ($semPacote) {
    beforeEach(function () use ($semPacote) {
        if (! $semPacote) {
            criarTabelaDeAuditoria();
        }
    });

    it('não grava nada enquanto a auditoria estiver desligada', function () {
        MenuItem::create(['name' => 'a', 'label' => 'A']);

        expect(auditorias())->toBeEmpty();
    })->skip($semPacote, 'Requer gsebastiao/laravel-auditable.');

    it('audita create, update, delete e restore feitos com MenuItem', function () {
        config()->set('menu.auditing.enabled', true);

        $item = MenuItem::create(['name' => 'a', 'label' => 'A']);
        $item->update(['label' => 'B']);
        $item->delete();
        $item->restore();

        // O restore() do Eloquent grava com save() (limpa o deleted_at), por
        // isso gera um 'updated' antes do 'restored' — tal como o trait
        // Auditable do próprio laravel-auditable.
        expect(auditorias()->pluck('event')->all())->toBe(['created', 'updated', 'deleted', 'updated', 'restored'])
            ->and(auditorias()[1]->changes)->toBe(['label' => ['old' => 'A', 'new' => 'B']])
            ->and(auditorias()->pluck('subject_type')->unique()->all())->toBe([MenuItem::class]);
    })->skip($semPacote, 'Requer gsebastiao/laravel-auditable.');

    it('grava o nome do item pai em vez do parent_id', function () {
        config()->set('menu.auditing.enabled', true);

        $pai = MenuItem::create(['name' => 'admin', 'label' => 'Administração']);
        MenuItem::create(['name' => 'users', 'label' => 'Utilizadores', 'parent_id' => $pai->id]);

        expect(auditorias()->last()->changes)
            ->toHaveKey('Item pai')
            ->not->toHaveKey('parent_id')
            ->and(auditorias()->last()->changes['Item pai'])->toBe(['id' => $pai->id, 'label' => 'Administração']);
    })->skip($semPacote, 'Requer gsebastiao/laravel-auditable.');

    it('$item->audits devolve o histórico do item', function () {
        config()->set('menu.auditing.enabled', true);

        $item = MenuItem::create(['name' => 'a', 'label' => 'A']);
        $item->update(['label' => 'B']);
        MenuItem::create(['name' => 'outro', 'label' => 'Outro']);

        expect($item->audits()->pluck('event')->sort()->values()->all())->toBe(['created', 'updated']);
    })->skip($semPacote, 'Requer gsebastiao/laravel-auditable.');

    it('não duplica linhas numa subclasse que já usa o trait Auditable', function () {
        config()->set('menu.auditing.enabled', true);

        MenuItemComTraitAuditable::create(['name' => 'a', 'label' => 'A']);

        expect(auditorias())->toHaveCount(1);
    })->skip($semPacote, 'Requer gsebastiao/laravel-auditable.');

    it('usa o getAuditOptions() de uma subclasse personalizada', function () {
        config()->set('menu.auditing.enabled', true);

        MenuItemPersonalizado::create(['name' => 'a', 'label' => 'A', 'badge' => 'novo']);

        expect(auditorias())->toHaveCount(1)
            ->and(auditorias()->first()->changes)->toHaveKey('label')
            ->not->toHaveKey('badge');
    })->skip($semPacote, 'Requer gsebastiao/laravel-auditable.');
});
