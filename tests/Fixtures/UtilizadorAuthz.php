<?php

declare(strict_types=1);

namespace Gsebastiao\LaravelMenu\Tests\Fixtures;

use Gsebastiao\LaravelAuthz\Traits\HasAuthz;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * Model de utilizador com o trait do gsebastiao/laravel-authz, como se
 * espera numa aplicação que use esse pacote.
 *
 * Só é carregado pelos testes que correm com o pacote instalado.
 */
class UtilizadorAuthz extends Authenticatable
{
    use HasAuthz;

    protected $table = 'users';

    protected $guarded = [];

    public $timestamps = false;
}
