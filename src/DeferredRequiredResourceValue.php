<?php

declare(strict_types=1);

namespace PhpSoftBox\Resource;

use Closure;

/** @internal */
final readonly class DeferredRequiredResourceValue
{
    public function __construct(
        private Closure $resolver,
    ) {
    }

    public function resolve(): mixed
    {
        return ($this->resolver)();
    }
}
