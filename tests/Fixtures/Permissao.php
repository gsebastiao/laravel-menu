<?php

declare(strict_types=1);

namespace Gsebastiao\LaravelMenu\Tests\Fixtures;

enum Permissao: string
{
    case VerUtilizadores = 'users.view';
    case EditarUtilizadores = 'users.edit';
}
