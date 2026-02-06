<?php

declare(strict_types=1);

namespace PhpSoftBox\Resource\Integration;

use PhpSoftBox\Orm\Contracts\EntityInterface;
use PhpSoftBox\Orm\Contracts\EntityRuntimeRegistryInterface;
use PhpSoftBox\Resource\RelationState;
use PhpSoftBox\Resource\RelationStateProviderInterface;

/**
 * Опциональная интеграция с phpsoftbox/orm.
 */
final readonly class OrmRelationStateProvider implements RelationStateProviderInterface
{
    public function __construct(
        private EntityRuntimeRegistryInterface $runtimeRegistry,
    ) {
    }

    public function relationState(object $resource, string $relation): RelationState
    {
        if (!$resource instanceof EntityInterface) {
            return RelationState::Unknown;
        }

        return $this->runtimeRegistry->node($resource)?->isRelationLoaded($relation) === true
            ? RelationState::Loaded
            : RelationState::Unloaded;
    }
}
