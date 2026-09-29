<?php

declare(strict_types=1);

namespace PhpSoftBox\Resource\Tests;

use PhpSoftBox\Resource\Resource;
use PhpSoftBox\Resource\ResourceSerializer;
use PhpSoftBox\Resource\Tests\Fixtures\ApiResponseUserResource;
use PhpSoftBox\Resource\Tests\Fixtures\ConditionalUserResource;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function json_encode;

use const JSON_THROW_ON_ERROR;

#[CoversClass(Resource::class)]
#[CoversMethod(Resource::class, 'jsonSerialize')]
final class ResourceJsonSerializeTest extends TestCase
{
    /**
     * Проверяет, что jsonSerialize возвращает null, если ресурс отсутствует.
     */
    #[Test]
    public function jsonSerializeReturnsNullWhenResourceIsNull(): void
    {
        $resource = new ApiResponseUserResource(null);

        self::assertNull($resource->jsonSerialize());
    }

    /**
     * Проверяет, что jsonSerialize возвращает массив, если ресурс задан.
     */
    #[Test]
    public function jsonSerializeReturnsArrayWhenResourceIsPresent(): void
    {
        $resource = new ApiResponseUserResource(['id' => 10]);

        self::assertSame(['id' => 10], $resource->jsonSerialize());
    }

    /**
     * Проверяет, что json_encode() ресурса идёт через ResourceSerializer: скрытые поля (MissingValue)
     * не попадают в JSON как `{}`.
     *
     * @see Resource::jsonSerialize()
     * @see ResourceSerializer::serialize()
     */
    #[Test]
    public function jsonEncodeOmitsHiddenFields(): void
    {
        $resource = new ConditionalUserResource(['name' => 'Arthur', 'role' => 'admin']);

        self::assertSame('{"name":"Arthur","role":"admin"}', json_encode($resource, JSON_THROW_ON_ERROR));
    }

    /**
     * Проверяет, что json_encode() коллекции ресурсов тоже удаляет скрытые поля элементов.
     *
     * @see Resource::jsonSerialize()
     */
    #[Test]
    public function jsonEncodeOmitsHiddenFieldsInCollection(): void
    {
        $collection = ConditionalUserResource::collection([['name' => 'Arthur']]);

        self::assertSame('[{"name":"Arthur"}]', json_encode($collection, JSON_THROW_ON_ERROR));
    }
}
