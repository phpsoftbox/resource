<?php

declare(strict_types=1);

namespace PhpSoftBox\Resource\Tests\Fixtures;

final class PivotAwareValue
{
    public function __construct(
        public string $name,
        private ?object $pivot = null,
        private ?object $membership = null,
    ) {
    }

    public function pivot(): ?object
    {
        return $this->pivot;
    }

    public function membership(): ?object
    {
        return $this->membership;
    }
}
