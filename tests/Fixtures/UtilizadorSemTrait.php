<?php

declare(strict_types=1);

namespace Gsebastiao\LaravelMenu\Tests\Fixtures;

use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * Model de utilizador a que falta o trait HasAuthz. Serve para verificar que
 * o menu consegue na mesma ler as permissões do gsebastiao/laravel-authz,
 * pelo id do utilizador.
 */
class UtilizadorSemTrait extends Authenticatable
{
    protected $table = 'users';

    protected $guarded = [];

    public $timestamps = false;
}
