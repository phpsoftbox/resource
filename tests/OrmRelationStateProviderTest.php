<?php

declare(strict_types=1);

namespace PhpSoftBox\Resource\Tests;

use PhpSoftBox\Orm\Contracts\EntityInterface;
use PhpSoftBox\Orm\UnitOfWork\EntityHeap;
use PhpSoftBox\Orm\UnitOfWork\EntityRuntimeRegistry;
use PhpSoftBox\Orm\UnitOfWork\UnitOfWork;
use PhpSoftBox\Resource\Integration\OrmRelationStateProvider;
use PhpSoftBox\Resource\Resource;
use PhpSoftBox\Resource\ResourceSerializer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(OrmRelationStateProvider::class)]
final class OrmRelationStateProviderTest extends TestCase
{
    #[Test]
    public function serializerResolvesRelationsAcrossDifferentUnitsOfWorkByEntityInstance(): void
    {
        $runtimeRegistry = new EntityRuntimeRegistry();

        $dispatcherUow = new UnitOfWork(new EntityHeap($runtimeRegistry));
        $tenantUow     = new UnitOfWork(new EntityHeap($runtimeRegistry));
        $dispatcher    = $this->entity(10, null);
        $tenant        = $this->entity(10, ['sku-1']);
        $serializer    = new ResourceSerializer(
            relationStateProvider: new OrmRelationStateProvider($runtimeRegistry),
        );

        $dispatcherUow->markManaged($dispatcher);
        $tenantUow->markManaged($tenant);
        $dispatcherUow->markRelationLoaded($dispatcher, 'items');

        self::assertSame(['items' => null], $serializer->serialize($this->resource($dispatcher)));
        self::assertSame([], $serializer->serialize($this->resource($tenant)));

        $tenantUow->markRelationLoaded($tenant, 'items');

        self::assertSame(['items' => ['sku-1']], $serializer->serialize($this->resource($tenant)));
        self::assertSame(['items' => null], $serializer->serialize($this->resource($dispatcher)));
    }

    private function entity(int $id, ?array $items): EntityInterface
    {
        return new class ($id, $items) implements EntityInterface {
            /** @param list<string>|null $items */
            public function __construct(
                public int $id,
                public ?array $items,
            ) {
            }

            public function id(): int
            {
                return $this->id;
            }
        };
    }

    private function resource(EntityInterface $entity): Resource
    {
        return new class ($entity) extends Resource {
            public function toArray(): array
            {
                return [
                    'items' => $this->whenLoaded('items'),
                ];
            }
        };
    }
}
