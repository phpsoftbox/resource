<?php

namespace PhpSoftBox\Resource;

final readonly class ResourceDropdownAdapter implements DropdownAwareInterface
{
    public function __construct(
        private array $dropdown,
    ) {
    }

    public function dropdown(): array
    {
        return $this->dropdown;
    }
}
