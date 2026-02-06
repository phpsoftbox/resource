<?php

declare(strict_types=1);

namespace PhpSoftBox\Resource;

final readonly class CompositeRelationStateProvider implements RelationStateProviderInterface
{
    /**
     * @param iterable<RelationStateProviderInterface> $providers
     */
    public function __construct(
        private iterable $providers,
    ) {
    }

    public function relationState(object $resource, string $relation): RelationState
    {
        $state = RelationState::Unknown;

        foreach ($this->providers as $provider) {
            $providerState = $provider->relationState($resource, $relation);
            if ($providerState === RelationState::Loaded) {
                return RelationState::Loaded;
            }

            if ($providerState === RelationState::Unloaded) {
                $state = RelationState::Unloaded;
            }
        }

        return $state;
    }
}
