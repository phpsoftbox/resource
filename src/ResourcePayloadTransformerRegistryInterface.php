<?php

declare(strict_types=1);

namespace PhpSoftBox\Resource;

interface ResourcePayloadTransformerRegistryInterface
{
    /**
     * @param class-string<ResourceInterface> $resourceType
     */
    public function register(
        string $resourceType,
        ResourcePayloadTransformerInterface $transformer,
        int $priority = 0,
    ): void;

    /**
     * @return list<ResourcePayloadTransformerInterface>
     */
    public function for(ResourceInterface $resource): array;
}
