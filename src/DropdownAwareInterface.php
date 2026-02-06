<?php

declare(strict_types=1);

namespace PhpSoftBox\Resource;

interface DropdownAwareInterface
{
    /**
     * @return array<int, array{value: string|int, label: string}>
     */
    public function dropdown(): array;
}
