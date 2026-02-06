<?php

declare(strict_types=1);

namespace PhpSoftBox\Resource\Tests;

use PhpSoftBox\Resource\ResourceCollection;
use PhpSoftBox\Resource\Tests\Fixtures\CollectionUserResource;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ResourceCollection::class)]
final class ResourceCollectionTest extends TestCase
{
    /**
     * Проверяет применение класса ресурса к коллекции.
     */
    #[Test]
    public function collectionCollectsResourceClass(): void
    {
        $items = [
            ['id' => 1],
            ['id' => 2],
        ];

        $collection = new ResourceCollection($items)->collects(CollectionUserResource::class);

        self::assertSame([['id' => 1], ['id' => 2]], $collection->toArray());
    }

    /**
     * Проверяет работу mapper для коллекции.
     */
    #[Test]
    public function collectionUsesMapper(): void
    {
        $items = [
            ['id' => 1],
            ['id' => 2],
        ];

        $collection = new ResourceCollection($items)->map(
            static fn (array $item): array => ['id' => $item['id'] * 10],
        );

        self::assertSame([['id' => 10], ['id' => 20]], $collection->toArray());
    }

    /**
     * Проверяет работу мета-данных у коллекции.
     */
    #[Test]
    public function collectionMetaIsStored(): void
    {
        $collection = new ResourceCollection([])
            ->withMeta(['total' => 1])
            ->mergeMeta(['page' => 2]);

        self::assertSame(['total' => 1, 'page' => 2], $collection->meta());
    }

    /**
     * Проверяет создание коллекции из результата пагинации.
     */
    #[Test]
    public function collectionFromPaginator(): void
    {
        $paginator = [
            'items' => [
                ['id' => 1],
                ['id' => 2],
            ],
            'total'   => 10,
            'page'    => 2,
            'perPage' => 2,
            'pages'   => 5,
        ];

        $collection = ResourceCollection::fromPaginator($paginator)->collects(CollectionUserResource::class);

        self::assertSame([['id' => 1], ['id' => 2]], $collection->toArray());
        self::assertSame(
            [
                'total'   => 10,
                'page'    => 2,
                'perPage' => 2,
                'pages'   => 5,
            ],
            $collection->meta(),
        );
    }
}
