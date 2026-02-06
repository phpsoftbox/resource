<?php

declare(strict_types=1);

namespace PhpSoftBox\Resource\Tests;

use PhpSoftBox\Resource\ApiResponse;
use PhpSoftBox\Resource\Tests\Fixtures\ConditionalUserResource;
use PhpSoftBox\Resource\Tests\Fixtures\PivotAwareResource;
use PhpSoftBox\Resource\Tests\Fixtures\PivotAwareValue;
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

    /**
     * Проверяет conditional helpers для exists-флага и агрегатов отношений.
     */
    #[Test]
    public function aggregateHelpersUseSnakeCaseAttributes(): void
    {
        $resource = new ConditionalUserResource([
            'name'            => 'Arthur',
            'posts_exists'    => true,
            'posts_likes_sum' => 8,
            'posts_likes_max' => 5,
        ]);

        $response = ApiResponse::success($resource);

        self::assertSame(
            [
                'data' => [
                    'name'          => 'Arthur',
                    'postsExists'   => true,
                    'postsLikesSum' => 8,
                    'postsLikesMax' => 5,
                ],
                'meta'   => [],
                'errors' => null,
            ],
            $response->toArray(),
        );
    }

    /**
     * Проверяет, что whenPivotLoaded пропускает отсутствующий pivot.
     */
    #[Test]
    public function whenPivotLoadedSkipsMissingPivot(): void
    {
        $resource = new PivotAwareResource(new PivotAwareValue('Arthur'));

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
     * Проверяет вывод default и custom pivot accessor.
     */
    #[Test]
    public function whenPivotLoadedUsesDefaultAndCustomAccessors(): void
    {
        $resource = new PivotAwareResource(new PivotAwareValue(
            name: 'Arthur',
            pivot: (object) ['createdDatetime' => '2026-07-21 12:00:00'],
            membership: (object) ['expiresDatetime' => '2026-08-21 12:00:00'],
        ));

        $response = ApiResponse::success($resource);

        self::assertSame(
            [
                'data' => [
                    'name'  => 'Arthur',
                    'pivot' => [
                        'createdDatetime' => '2026-07-21 12:00:00',
                    ],
                    'membership' => [
                        'expiresDatetime' => '2026-08-21 12:00:00',
                    ],
                ],
                'meta'   => [],
                'errors' => null,
            ],
            $response->toArray(),
        );
    }
}
