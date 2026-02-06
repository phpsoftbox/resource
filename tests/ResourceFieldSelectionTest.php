<?php

declare(strict_types=1);

namespace PhpSoftBox\Resource\Tests;

use PhpSoftBox\Resource\ResourceCollection;
use PhpSoftBox\Resource\ResourceFieldFilter;
use PhpSoftBox\Resource\ResourceFieldSelection;
use PhpSoftBox\Resource\Tests\Fixtures\FieldUserResource;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ResourceFieldFilter::class)]
#[CoversClass(ResourceFieldSelection::class)]
#[CoversClass(ResourceCollection::class)]
final class ResourceFieldSelectionTest extends TestCase
{
    /**
     * Проверяет, что only() оставляет только выбранные поля одиночного ресурса.
     */
    #[Test]
    public function onlyFiltersSingleResourceFields(): void
    {
        $resource = new FieldUserResource($this->user());

        self::assertSame(
            [
                'id'   => 1,
                'name' => 'Anton',
            ],
            $resource->only('id', 'name')->toArray(),
        );
    }

    /**
     * Проверяет, что except() исключает выбранные поля одиночного ресурса.
     */
    #[Test]
    public function exceptFiltersSingleResourceFields(): void
    {
        $resource = new FieldUserResource($this->user());

        self::assertSame(
            [
                'id'    => 1,
                'name'  => 'Anton',
                'email' => 'anton@example.test',
            ],
            $resource->except('secret')->toArray(),
        );
    }

    /**
     * Проверяет, что only() можно применять к каждому элементу ResourceCollection.
     */
    #[Test]
    public function onlyFiltersCollectionItemFields(): void
    {
        $collection = FieldUserResource::collection([$this->user(), $this->anotherUser()])
            ->only(['id', 'email']);

        self::assertSame(
            [
                ['id' => 1, 'email' => 'anton@example.test'],
                ['id' => 2, 'email' => 'demo@example.test'],
            ],
            $collection->toArray(),
        );
    }

    /**
     * Проверяет, что except() можно применять к каждому элементу ResourceCollection.
     */
    #[Test]
    public function exceptFiltersCollectionItemFields(): void
    {
        $collection = FieldUserResource::collection([$this->user(), $this->anotherUser()])
            ->except('secret', 'email');

        self::assertSame(
            [
                ['id' => 1, 'name' => 'Anton'],
                ['id' => 2, 'name' => 'Demo'],
            ],
            $collection->toArray(),
        );
    }

    /**
     * Проверяет, что except() применяется после only().
     */
    #[Test]
    public function exceptIsAppliedAfterOnly(): void
    {
        $resource = new FieldUserResource($this->user());

        self::assertSame(
            [
                'id' => 1,
            ],
            $resource->only('id', 'name')->except('name')->toArray(),
        );
    }

    /**
     * @return array{id:int,name:string,email:string,secret:string}
     */
    private function user(): array
    {
        return [
            'id'     => 1,
            'name'   => 'Anton',
            'email'  => 'anton@example.test',
            'secret' => 'hidden',
        ];
    }

    /**
     * @return array{id:int,name:string,email:string,secret:string}
     */
    private function anotherUser(): array
    {
        return [
            'id'     => 2,
            'name'   => 'Demo',
            'email'  => 'demo@example.test',
            'secret' => 'hidden',
        ];
    }
}
