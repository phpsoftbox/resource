<?php

declare(strict_types=1);

namespace PhpSoftBox\Resource\Tests;

use PhpSoftBox\Resource\ApiResponse;
use PhpSoftBox\Resource\Tests\Fixtures\ConditionalUserResource;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ApiResponse::class)]
final class ResourceConditionalTest extends TestCase
{
    /**
     * Проверяет условный вывод атрибутов через when.
     */
    #[Test]
    public function whenSkipsMissingValues(): void
    {
        $resource = new ConditionalUserResource(['name' => 'Arthur']);

        $response = ApiResponse::success($resource);

        self::assertSame(
            [
                'data' => [
                    'name' => 'Arthur',
                ],
                'meta'   => [],
                'errors' => null,
            ],
            $response->toArray(),
        );
    }

    /**
     * Проверяет whenLoaded для массивов.
     */
    #[Test]
    public function whenLoadedUsesPresentAttributes(): void
    {
        $resource = new ConditionalUserResource(['name' => 'Arthur', 'role' => 'admin']);

        $response = ApiResponse::success($resource);

        self::assertSame(
            [
                'data' => [
                    'name' => 'Arthur',
                    'role' => 'admin',
                ],
                'meta'   => [],
                'errors' => null,
            ],
            $response->toArray(),
        );
    }

    /**
     * Проверяет whenCounted для счётчиков.
     */
    #[Test]
    public function whenCountedUsesSnakeCaseCountAttribute(): void
    {
        $resource = new ConditionalUserResource(['name' => 'Arthur', 'posts_count' => 2]);

        $response = ApiResponse::success($resource);

        self::assertSame(
            [
                'data' => [
                    'name'       => 'Arthur',
                    'postsCount' => 2,
                ],
                'meta'   => [],
                'errors' => null,
            ],
            $response->toArray(),
        );
    }
}
