<?php

declare(strict_types=1);

namespace PhpSoftBox\Resource;

interface RelationStateProviderInterface
{
    public function relationState(object $resource, string $relation): RelationState;
}
