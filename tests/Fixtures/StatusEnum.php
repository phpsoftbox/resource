<?php

declare(strict_types=1);

namespace PhpSoftBox\Resource\Tests\Fixtures;

use PhpSoftBox\Resource\EnumOptions;

enum StatusEnum: string
{
    use EnumOptions;

    case ACTIVE   = 'active';
    case INACTIVE = 'inactive';
    case DRAFT    = 'draft';

    public static function exceptCasesFromDropdown(): array
    {
        return [self::DRAFT];
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::ACTIVE   => 'Активен',
            self::INACTIVE => 'Неактивен',
            self::DRAFT    => 'Черновик',
        };
    }
}
