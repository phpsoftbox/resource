<?php

declare(strict_types=1);

namespace PhpSoftBox\Resource;

interface DropdownAwareInterface
{
    /**
     * @return array<int, array{value: string|int|null, label: string, meta?: array<string, mixed>}>
     */
    public function dropdown(): array;
}
