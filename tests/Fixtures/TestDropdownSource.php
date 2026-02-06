<?php

declare(strict_types=1);

namespace PhpSoftBox\Resource\Tests\Fixtures;

use PhpSoftBox\Resource\DropdownAwareInterface;

final class TestDropdownSource implements DropdownAwareInterface
{
    public function dropdown(): array
    {
        return [
            ['value' => 1, 'label' => 'One'],
            ['value' => 2, 'label' => 'Two'],
        ];
    }
}
