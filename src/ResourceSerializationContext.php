<?php

declare(strict_types=1);

namespace PhpSoftBox\Resource;

final readonly class ResourceSerializationContext
{
    public function __construct(
        public ?RelationStateProviderInterface $relationStateProvider = null,
    ) {
    }
}
