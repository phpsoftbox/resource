<?php

declare(strict_types=1);

namespace PhpSoftBox\Resource\Tests\Fixtures;

use PhpSoftBox\Resource\EnumOptions;

enum SimpleEnum: string
{
    use EnumOptions;

    case FIRST  = 'FIRST';
    case SECOND = 'SECOND';
}
