<?php

declare(strict_types=1);

namespace PhpSoftBox\Resource;

use InvalidArgumentException;

use function array_map;
use function array_values;
use function is_a;
use function spl_object_id;
use function usort;

final class ResourcePayloadTransformerRegistry implements ResourcePayloadTransformerRegistryInterface
{
    /**
     * @var list<array{
     *     resourceType: class-string<ResourceInterface>,
     *     transformer: ResourcePayloadTransformerInterface,
     *     priority: int,
     *     order: int
     * }>
     */
    private array $registrations = [];

    /**
     * @var array<class-string<ResourceInterface>, list<ResourcePayloadTransformerInterface>>
     */
    private array $resolved = [];

    /**
     * @var array<string, true>
     */
    private array $registeredObjects = [];

    private int $nextOrder = 0;

    public function register(
        string $resourceType,
        ResourcePayloadTransformerInterface $transformer,
        int $priority = 0,
    ): void {
        if (!is_a($resourceType, ResourceInterface::class, true)) {
            throw new InvalidArgumentException('Resource transformer type must implement ResourceInterface.');
        }

        $registrationKey = $resourceType . ':' . spl_object_id($transformer);
        if (isset($this->registeredObjects[$registrationKey])) {
            return;
        }

        $this->registeredObjects[$registrationKey] = true;
        $this->registrations[]                     = [
            'resourceType' => $resourceType,
            'transformer'  => $transformer,
            'priority'     => $priority,
            'order'        => $this->nextOrder++,
        ];
        $this->resolved = [];
    }

    public function for(ResourceInterface $resource): array
    {
        $resourceClass = $resource::class;

        if (isset($this->resolved[$resourceClass])) {
            return $this->resolved[$resourceClass];
        }

        $matched = [];
        foreach ($this->registrations as $registration) {
            if ($resource instanceof $registration['resourceType']) {
                $matched[] = $registration;
            }
        }

        usort(
            $matched,
            static fn (array $left, array $right): int => ($right['priority'] <=> $left['priority']) ?: ($left['order'] <=> $right['order']),
        );

        return $this->resolved[$resourceClass] = array_values(
            array_map(
                static fn (array $registration): ResourcePayloadTransformerInterface => $registration['transformer'],
                $matched,
            ),
        );
    }
}
