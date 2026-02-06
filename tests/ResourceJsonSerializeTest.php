<?php

declare(strict_types=1);

namespace PhpSoftBox\Resource\Tests;

use PhpSoftBox\Resource\Resource;
use PhpSoftBox\Resource\Tests\Fixtures\ApiResponseUserResource;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

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
}
