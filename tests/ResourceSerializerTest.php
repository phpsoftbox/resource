<?php

declare(strict_types=1);

namespace PhpSoftBox\Resource\Tests;

use LogicException;
use PhpSoftBox\Resource\MissingValue;
use PhpSoftBox\Resource\Resource;
use PhpSoftBox\Resource\ResourceCollection;
use PhpSoftBox\Resource\ResourceInterface;
use PhpSoftBox\Resource\ResourcePayloadTransformerInterface;
use PhpSoftBox\Resource\ResourcePayloadTransformerRegistry;
use PhpSoftBox\Resource\ResourceSerializer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ResourceSerializer::class)]
final class ResourceSerializerTest extends TestCase
{
    #[Test]
    public function serializesNestedResourcesAndCollectionsAtAnyDepth(): void
    {
        $registry = new ResourcePayloadTransformerRegistry();

        $registry->register(SerializerProductResource::class, new AddPayloadValueTransformer('url', '/products/1'));

        $resource = new SerializerShipmentResource([
            'product' => ['id' => 1],
            'items'   => [['id' => 2], ['id' => 3]],
        ]);

        self::assertSame(
            [
                'product' => [
                    'product' => ['id' => 1, 'url' => '/products/1'],
                ],
                'nested' => [
                    'items' => [
                        'data' => [
                            ['id' => 2, 'url' => '/products/1'],
                            ['id' => 3, 'url' => '/products/1'],
                        ],
                    ],
                ],
            ],
            new ResourceSerializer($registry)->serialize($resource),
        );
    }

    #[Test]
    public function keepsRegistryAndFluentDecoratorOrder(): void
    {
        $registry = new ResourcePayloadTransformerRegistry();

        $registry->register(
            SerializerProductResource::class,
            new AddPayloadValueTransformer('registry', true),
        );

        $resource = new SerializerProductResource(['id' => 1])
            ->only('id', 'registry')
            ->through(static fn (array $payload): array => $payload + ['explicit' => true]);

        self::assertSame(
            ['id' => 1, 'registry' => true, 'explicit' => true],
            new ResourceSerializer($registry)->serialize($resource),
        );
    }

    #[Test]
    public function excludesSelectedTransformerForTargetedResourcesInParentTree(): void
    {
        $registry = new ResourcePayloadTransformerRegistry();
        $excluded = new AddUrlPayloadTransformer('url', '/products/1');
        $kept     = new AddPayloadValueTransformer('available', true);
        $registry->register(SerializerProductResource::class, $excluded, 20);
        $registry->register(SerializerProductResource::class, $kept, 10);

        $shipment = new SerializerShipmentResource([
            'product' => ['id' => 1],
            'items'   => [['id' => 2]],
        ])->withoutRegisteredTransformersFor(
            SerializerProductResource::class,
            [AddUrlTransformerMarker::class],
        );

        $outside = new SerializerProductResource(['id' => 9]);

        $serializer = new ResourceSerializer($registry);

        self::assertSame(
            [
                'shipment' => [
                    'shipment' => [
                        'product' => [
                            'product' => ['id' => 1, 'available' => true],
                        ],
                        'nested' => [
                            'items' => [
                                'data' => [
                                    ['id' => 2, 'available' => true],
                                ],
                            ],
                        ],
                    ],
                ],
                'outside' => [
                    'product' => ['id' => 9, 'url' => '/products/1', 'available' => true],
                ],
            ],
            $serializer->serialize([
                'shipment' => $shipment,
                'outside'  => $outside,
            ]),
        );
    }

    #[Test]
    public function emptyExclusionListSkipsAllRegisteredTransformersButKeepsThrough(): void
    {
        $registry = new ResourcePayloadTransformerRegistry();

        $registry->register(
            SerializerProductResource::class,
            new AddPayloadValueTransformer('registry', true),
        );

        $resource = new SerializerShipmentResource([
            'product' => ['id' => 1],
            'items'   => [],
        ])->withoutRegisteredTransformersFor(SerializerProductResource::class)
            ->through(static fn (array $payload): array => $payload + ['explicit' => true]);

        self::assertSame(
            [
                'product' => [
                    'product' => ['id' => 1],
                ],
                'nested'   => ['items' => ['data' => []]],
                'explicit' => true,
            ],
            new ResourceSerializer($registry)->serialize($resource),
        );
    }

    #[Test]
    public function inheritedExclusionsApplyToResourceAddedByTransformer(): void
    {
        $registry = new ResourcePayloadTransformerRegistry();

        $registry->register(
            SerializerShipmentResource::class,
            new class () implements ResourcePayloadTransformerInterface {
                public function transform(array $payload, ResourceInterface $resource): array
                {
                    $payload['added'] = new SerializerProductResource(['id' => 10]);

                    return $payload;
                }
            },
        );
        $registry->register(
            SerializerProductResource::class,
            new AddPayloadValueTransformer('url', '/products/10'),
        );

        $resource = new SerializerShipmentResource([
            'product' => ['id' => 1],
            'items'   => [],
        ])->withoutRegisteredTransformersFor(SerializerProductResource::class);

        self::assertSame(
            ['id' => 10],
            new ResourceSerializer($registry)->serialize($resource)['added']['product'],
        );
    }

    #[Test]
    public function exclusionDoesNotAffectThroughOnTargetedNestedResource(): void
    {
        $registry = new ResourcePayloadTransformerRegistry();

        $registry->register(
            SerializerProductResource::class,
            new AddPayloadValueTransformer('registry', true),
        );

        $resource = new class ([]) extends Resource {
            public function toArray(): array
            {
                return [
                    'product' => new SerializerProductResource(['id' => 1])
                        ->through(static fn (array $payload): array => $payload + ['explicit' => true]),
                ];
            }
        };

        $resource = $resource->withoutRegisteredTransformersFor(SerializerProductResource::class);

        self::assertSame(
            [
                'product' => [
                    'product' => ['id' => 1, 'explicit' => true],
                ],
            ],
            new ResourceSerializer($registry)->serialize($resource),
        );
    }

    #[Test]
    public function collectionFieldSelectionRunsAfterItemRegistryTransformers(): void
    {
        $registry = new ResourcePayloadTransformerRegistry();

        $registry->register(
            SerializerProductResource::class,
            new AddPayloadValueTransformer('registry', true),
        );

        $collection = new ResourceCollection([['id' => 1]])
            ->collects(SerializerProductResource::class)
            ->only('registry');

        self::assertSame(
            [['registry' => true]],
            new ResourceSerializer($registry)->serialize($collection),
        );
    }

    #[Test]
    public function removesMissingValuesAddedByResourcesAndTransformers(): void
    {
        $registry = new ResourcePayloadTransformerRegistry();

        $registry->register(
            SerializerProductResource::class,
            new class () implements ResourcePayloadTransformerInterface {
                public function transform(array $payload, ResourceInterface $resource): array
                {
                    $payload['missing'] = new MissingValue();

                    return $payload;
                }
            },
        );

        self::assertSame(
            ['id' => 1],
            new ResourceSerializer($registry)->serialize(new SerializerProductResource(['id' => 1])),
        );
    }

    #[Test]
    public function detectsCyclicResourceTree(): void
    {
        $resource = new SerializerCyclicResource([]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Cyclic resource serialization detected');

        new ResourceSerializer()->serialize($resource);
    }

    #[Test]
    public function reportsNonArrayRegisteredTransformerResultAsLogicError(): void
    {
        $registry = new ResourcePayloadTransformerRegistry();

        $registry->register(
            SerializerProductResource::class,
            new class () implements ResourcePayloadTransformerInterface {
                public function transform(array $payload, ResourceInterface $resource): array
                {
                    /** @phpstan-ignore return.type */
                    return 'invalid';
                }
            },
        );

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(
            'Registered resource payload transformer must return array payload, got string.',
        );

        new ResourceSerializer($registry)->serialize(new SerializerProductResource(['id' => 1]));
    }
}

interface AddUrlTransformerMarker extends ResourcePayloadTransformerInterface
{
}

class AddPayloadValueTransformer implements ResourcePayloadTransformerInterface
{
    public function __construct(
        private readonly string $key,
        private readonly mixed $value,
    ) {
    }

    public function transform(array $payload, ResourceInterface $resource): array
    {
        $payload[$this->key] = $this->value;

        return $payload;
    }
}

final class AddUrlPayloadTransformer extends AddPayloadValueTransformer implements AddUrlTransformerMarker
{
}

final class SerializerProductResource extends Resource
{
    protected ?string $wrapper = 'product';

    public function toArray(): array
    {
        return ['id' => $this->resource['id']];
    }
}

final class SerializerShipmentResource extends Resource
{
    protected ?string $wrapper = 'shipment';

    public function toArray(): array
    {
        return [
            'product' => new SerializerProductResource($this->resource['product']),
            'nested'  => [
                'items' => new ResourceCollection($this->resource['items'])
                    ->collects(SerializerProductResource::class),
            ],
        ];
    }
}

final class SerializerCyclicResource extends Resource
{
    public function toArray(): array
    {
        return ['self' => $this];
    }
}
