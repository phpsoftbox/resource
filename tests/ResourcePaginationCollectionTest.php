<?php

declare(strict_types=1);

namespace PhpSoftBox\Resource\Tests;

use PhpSoftBox\Pagination\Paginator;
use PhpSoftBox\Resource\ApiResponse;
use PhpSoftBox\Resource\Tests\Fixtures\CollectionUserResource;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ApiResponse::class)]
final class ResourcePaginationCollectionTest extends TestCase
{
    /**
     * Проверяет коллекцию ресурсов на базе пагинации.
     */
    #[Test]
    public function resourceCollectionFromPagination(): void
    {
        $pagination = new Paginator(perPage: 2)
            ->path('/shipments')
            ->make(items: [['id' => 1], ['id' => 2]], total: 3, page: 1);

        $collection = CollectionUserResource::collection($pagination);

        self::assertSame(
            [
                'data' => [
                    ['id' => 1],
                    ['id' => 2],
                ],
                'links' => [
                    'first' => '/shipments?page=1',
                    'last'  => '/shipments?page=2',
                    'next'  => '/shipments?page=2',
                ],
                'meta' => [
                    'current_page' => 1,
                    'from'         => 1,
                    'last_page'    => 2,
                    'links'        => [
                        [
                            'active' => false,
                            'label'  => 'Previous',
                            'url'    => null,
                        ],
                        [
                            'active' => true,
                            'label'  => '1',
                            'url'    => '/shipments?page=1',
                        ],
                        [
                            'active' => false,
                            'label'  => '2',
                            'url'    => '/shipments?page=2',
                        ],
                        [
                            'active' => false,
                            'label'  => 'Next',
                            'url'    => '/shipments?page=2',
                        ],
                    ],
                    'path'     => '/shipments',
                    'per_page' => 2,
                    'to'       => 2,
                    'total'    => 3,
                ],
            ],
            $collection->toArray(),
        );
    }

    /**
     * Проверяет вывод пагинации внутри ApiResponse.
     */
    #[Test]
    public function apiResponseKeepsPaginationStructure(): void
    {
        $pagination = new Paginator(perPage: 2)
            ->path('/shipments')
            ->make(items: [['id' => 1]], total: 1, page: 1);

        $response = ApiResponse::success([
            'shipments' => CollectionUserResource::collection($pagination),
        ]);

        self::assertSame(
            [
                'data' => [
                    'shipments' => [
                        'data' => [
                            ['id' => 1],
                        ],
                        'links' => [
                            'first' => '/shipments?page=1',
                            'last'  => '/shipments?page=1',
                        ],
                        'meta' => [
                            'current_page' => 1,
                            'from'         => 1,
                            'last_page'    => 1,
                            'links'        => [
                                [
                                    'active' => false,
                                    'label'  => 'Previous',
                                    'url'    => null,
                                ],
                                [
                                    'active' => true,
                                    'label'  => '1',
                                    'url'    => '/shipments?page=1',
                                ],
                                [
                                    'active' => false,
                                    'label'  => 'Next',
                                    'url'    => null,
                                ],
                            ],
                            'path'     => '/shipments',
                            'per_page' => 2,
                            'to'       => 1,
                            'total'    => 1,
                        ],
                    ],
                ],
                'meta'   => [],
                'errors' => null,
            ],
            $response->toArray(),
        );
    }
}
