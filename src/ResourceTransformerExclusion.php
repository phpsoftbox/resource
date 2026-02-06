<?php

declare(strict_types=1);

namespace PhpSoftBox\Resource;

final class ResourceTransformerExclusion extends Resource implements ResourceTransformerExclusionInterface
{
    /**
     * @param class-string<ResourceInterface> $resourceType
     * @param list<class-string<ResourcePayloadTransformerInterface>> $transformerTypes
     */
    public function __construct(
        private readonly ResourceInterface $inner,
        private readonly string $resourceType,
        private readonly array $transformerTypes,
    ) {
        parent::__construct($inner);
        $this->wrapper = $inner->wrapper();
    }

    public function inner(): ResourceInterface
    {
        return $this->inner;
    }

    public function decorate(array $payload): array
    {
        return $payload;
    }

    public function toArray(): array
    {
        return $this->inner->toArray();
    }

    public function meta(): array
    {
        return $this->inner->meta();
    }

    public function transformerExclusionRules(): array
    {
        return [[
            'resourceType'     => $this->resourceType,
            'transformerTypes' => $this->transformerTypes,
        ]];
    }
}
