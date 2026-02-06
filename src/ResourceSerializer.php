<?php

declare(strict_types=1);

namespace PhpSoftBox\Resource;

use LogicException;
use TypeError;

use function array_is_list;
use function array_reverse;
use function array_values;
use function get_debug_type;
use function interface_exists;
use function is_array;
use function preg_match;
use function spl_object_id;
use function str_contains;

final class ResourceSerializer implements ResourceSerializerInterface
{
    private readonly ResourcePayloadTransformerRegistryInterface $registry;

    private readonly ResourceSerializationContext $context;

    public function __construct(
        ?ResourcePayloadTransformerRegistryInterface $registry = null,
        ?RelationStateProviderInterface $relationStateProvider = null,
    ) {
        $this->registry = $registry ?? new ResourcePayloadTransformerRegistry();
        $this->context  = new ResourceSerializationContext($relationStateProvider);
    }

    public function serialize(mixed $value): mixed
    {
        $activeResources = [];

        return $this->normalize($value, false, [], $activeResources);
    }

    /**
     * @param list<array{
     *     resourceType: class-string<ResourceInterface>,
     *     transformerTypes: list<class-string<ResourcePayloadTransformerInterface>>
     * }> $exclusionRules
     * @param array<int, true> $activeResources
     */
    private function normalize(
        mixed $value,
        bool $wrapResource,
        array $exclusionRules,
        array &$activeResources,
    ): mixed {
        $paginationInterface = 'PhpSoftBox\\Pagination\\Contracts\\PaginationResultInterface';

        if ($value instanceof MissingValue) {
            return $value;
        }

        if ($value instanceof DeferredRequiredResourceValue) {
            return $this->normalize($value->resolve(), $wrapResource, $exclusionRules, $activeResources);
        }

        if (interface_exists($paginationInterface) && $value instanceof $paginationInterface) {
            return $this->normalize($value->toArray(), false, $exclusionRules, $activeResources);
        }

        if ($value instanceof ResourceInterface) {
            return $this->normalizeResource($value, $wrapResource, $exclusionRules, $activeResources);
        }

        if (is_array($value)) {
            return $this->normalizeArray($value, $exclusionRules, $activeResources);
        }

        return $value;
    }

    /**
     * @param list<array{
     *     resourceType: class-string<ResourceInterface>,
     *     transformerTypes: list<class-string<ResourcePayloadTransformerInterface>>
     * }> $inheritedExclusionRules
     * @param array<int, true> $activeResources
     */
    private function normalizeResource(
        ResourceInterface $resource,
        bool $wrapResource,
        array $inheritedExclusionRules,
        array &$activeResources,
    ): mixed {
        $outerResource  = $resource;
        $baseResource   = $resource;
        $decorators     = [];
        $exclusionRules = $inheritedExclusionRules;

        while ($baseResource instanceof ResourceDecoratorInterface) {
            if ($baseResource instanceof ResourceTransformerExclusionInterface) {
                $exclusionRules = [
                    ...$exclusionRules,
                    ...$baseResource->transformerExclusionRules(),
                ];
            } else {
                $decorators[] = $baseResource;
            }

            $baseResource = $baseResource->inner();
        }

        $resourceId = $baseResource instanceof Resource
            ? $baseResource->serializationIdentity()
            : spl_object_id($baseResource);
        if (isset($activeResources[$resourceId])) {
            throw new LogicException('Cyclic resource serialization detected for ' . $baseResource::class . '.');
        }

        $activeResources[$resourceId] = true;

        try {
            if ($baseResource instanceof Resource && $baseResource->resource() === null) {
                return null;
            }

            if ($baseResource instanceof ResourceCollection) {
                $payload = $this->normalizeCollection($baseResource, $exclusionRules, $activeResources);
            } else {
                $payload = $baseResource instanceof Resource
                    ? $baseResource->toArrayWithContext($this->context)
                    : $baseResource->toArray();

                foreach ($decorators as $decorator) {
                    if ($decorator instanceof ResourceFieldSelection) {
                        $payload = $decorator->discardExcludedDeferredValues($payload);
                    }
                }

                $payload = $this->normalizeArray($payload, $exclusionRules, $activeResources);
            }

            foreach ($this->registry->for($baseResource) as $transformer) {
                if ($this->isExcluded($baseResource, $transformer, $exclusionRules)) {
                    continue;
                }

                $payload = $this->transform($transformer, $payload, $baseResource);
                $payload = $this->normalizeArrayResult(
                    $payload,
                    $exclusionRules,
                    $activeResources,
                    'Registered resource payload transformer',
                );
            }

            foreach (array_reverse($decorators) as $decorator) {
                $payload = $decorator->decorate($payload);
                $payload = $this->normalizeArrayResult(
                    $payload,
                    $exclusionRules,
                    $activeResources,
                    'Resource payload decorator',
                );
            }

            if (!$wrapResource) {
                return $payload;
            }

            $wrapper = $outerResource->wrapper();
            if ($wrapper === null || $wrapper === '') {
                return $payload;
            }

            $envelope = [$wrapper => $payload];
            $meta     = $outerResource->meta();

            if ($meta !== []) {
                $envelope['meta'] = $this->normalizeArray($meta, $exclusionRules, $activeResources);
            }

            return $envelope;
        } finally {
            unset($activeResources[$resourceId]);
        }
    }

    /**
     * @param list<array{
     *     resourceType: class-string<ResourceInterface>,
     *     transformerTypes: list<class-string<ResourcePayloadTransformerInterface>>
     * }> $exclusionRules
     * @param array<int, true> $activeResources
     * @return array<string|int, mixed>
     */
    private function normalizeCollection(
        ResourceCollection $collection,
        array $exclusionRules,
        array &$activeResources,
    ): array {
        $items = [];
        foreach ($collection->itemsForSerialization() as $item) {
            if ($item instanceof ResourceInterface) {
                $item = $collection->applyFieldSelection($item);
            }

            $item = $this->normalize($item, false, $exclusionRules, $activeResources);

            if ($item instanceof MissingValue) {
                continue;
            }

            $items[] = $collection->filterSerializedItem($item);
        }

        if (!$collection->hasPagination()) {
            return $items;
        }

        return [
            'data'  => $items,
            'links' => $this->normalizeArray($collection->paginationLinks(), $exclusionRules, $activeResources),
            'meta'  => $this->normalizeArray($collection->meta(), $exclusionRules, $activeResources),
        ];
    }

    /**
     * @param array<string|int, mixed> $value
     * @param list<array{
     *     resourceType: class-string<ResourceInterface>,
     *     transformerTypes: list<class-string<ResourcePayloadTransformerInterface>>
     * }> $exclusionRules
     * @param array<int, true> $activeResources
     * @return array<string|int, mixed>
     */
    private function normalizeArray(array $value, array $exclusionRules, array &$activeResources): array
    {
        $isList = array_is_list($value);

        foreach ($value as $key => $item) {
            $item = $this->normalize($item, true, $exclusionRules, $activeResources);

            if ($item instanceof MissingValue) {
                unset($value[$key]);
                continue;
            }

            $value[$key] = $item;
        }

        return $isList ? array_values($value) : $value;
    }

    /**
     * @param list<array{
     *     resourceType: class-string<ResourceInterface>,
     *     transformerTypes: list<class-string<ResourcePayloadTransformerInterface>>
     * }> $exclusionRules
     */
    private function isExcluded(
        ResourceInterface $resource,
        ResourcePayloadTransformerInterface $transformer,
        array $exclusionRules,
    ): bool {
        foreach ($exclusionRules as $rule) {
            if (!$resource instanceof $rule['resourceType']) {
                continue;
            }

            if ($rule['transformerTypes'] === []) {
                return true;
            }

            foreach ($rule['transformerTypes'] as $transformerType) {
                if ($transformer instanceof $transformerType) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param list<array{
     *     resourceType: class-string<ResourceInterface>,
     *     transformerTypes: list<class-string<ResourcePayloadTransformerInterface>>
     * }> $exclusionRules
     * @param array<int, true> $activeResources
     * @return array<string|int, mixed>
     */
    private function normalizeArrayResult(
        mixed $payload,
        array $exclusionRules,
        array &$activeResources,
        string $source,
    ): array {
        if (!is_array($payload)) {
            throw new LogicException($source . ' must return array payload, got ' . get_debug_type($payload) . '.');
        }

        return $this->normalizeArray($payload, $exclusionRules, $activeResources);
    }

    /**
     * @param array<string|int, mixed> $payload
     * @return array<string|int, mixed>
     */
    private function transform(
        ResourcePayloadTransformerInterface $transformer,
        array $payload,
        ResourceInterface $resource,
    ): array {
        try {
            return $transformer->transform($payload, $resource);
        } catch (TypeError $exception) {
            $message = $exception->getMessage();

            if (!str_contains($message, 'Return value must be of type array')) {
                throw $exception;
            }

            $type = 'unknown';
            if (preg_match('/array, ([^ ]+) returned$/', $message, $matches) === 1) {
                $type = $matches[1];
            }

            throw new LogicException(
                'Registered resource payload transformer must return array payload, got ' . $type . '.',
                previous: $exception,
            );
        }
    }
}
