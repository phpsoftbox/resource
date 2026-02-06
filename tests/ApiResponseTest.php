<?php

declare(strict_types=1);

namespace PhpSoftBox\Resource\Tests;

use PhpSoftBox\Resource\ApiResponse;
use PhpSoftBox\Resource\ErrorBag;
use PhpSoftBox\Resource\ResourceCollection;
use PhpSoftBox\Resource\Tests\Fixtures\ApiResponseUserResource;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ApiResponse::class)]
#[CoversClass(ErrorBag::class)]
final class ApiResponseTest extends TestCase
{
    /**
     * Проверяет envelope успешного ответа.
     */
    #[Test]
    public function successEnvelopeHasAllKeys(): void
    {
        $response = ApiResponse::success(['id' => 1], ['trace_id' => 'abc']);

        self::assertSame(
            [
                'data'   => ['id' => 1],
                'meta'   => ['trace_id' => 'abc'],
                'errors' => null,
            ],
            $response->toArray(),
        );
    }

    /**
     * Проверяет envelope ответа с ошибкой.
     */
    #[Test]
    public function errorEnvelopeContainsMessageAndFields(): void
    {
        $response = ApiResponse::error(
            message: 'Данные не прошли валидацию.',
            fields: ['email' => ['Некорректный email.']],
            meta: ['trace_id' => 'abc'],
            code: 'validation',
        );

        self::assertSame(
            [
                'data'   => null,
                'meta'   => ['trace_id' => 'abc'],
                'errors' => [
                    'message' => 'Данные не прошли валидацию.',
                    'fields'  => ['email' => ['Некорректный email.']],
                    'code'    => 'validation',
                ],
            ],
            $response->toArray(),
        );
    }

    /**
     * Проверяет слияние мета-данных ресурса и ответа.
     */
    #[Test]
    public function resourceMetaIsMergedWithResponseMeta(): void
    {
        $resource = new ApiResponseUserResource(['id' => 1]);

        $response = ApiResponse::success($resource, ['trace_id' => 'abc', 'source' => 'override']);

        self::assertSame(
            [
                'data'   => ['id' => 1],
                'meta'   => ['source' => 'override', 'trace_id' => 'abc'],
                'errors' => null,
            ],
            $response->toArray(),
        );
    }

    /**
     * Проверяет нормализацию ресурсов внутри массива данных.
     */
    #[Test]
    public function nestedResourcesAreNormalized(): void
    {
        $payload = [
            'users' => new ResourceCollection([
                ['id' => 1],
                ['id' => 2],
            ])->collects(ApiResponseUserResource::class),
            'filters' => ['active' => true],
        ];

        $response = ApiResponse::success($payload);

        self::assertSame(
            [
                'data' => [
                    'users' => [
                        'data' => [
                            ['id' => 1],
                            ['id' => 2],
                        ],
                    ],
                    'filters' => ['active' => true],
                ],
                'meta'   => [],
                'errors' => null,
            ],
            $response->toArray(),
        );
    }

    /**
     * Проверяет отключение wrapper для вложенного ресурса.
     */
    #[Test]
    public function nestedResourceWithoutWrapper(): void
    {
        $payload = [
            'user' => new ApiResponseUserResource(['id' => 1])->withoutWrapper(),
        ];

        $response = ApiResponse::success($payload);

        self::assertSame(
            [
                'data' => [
                    'user' => ['id' => 1],
                ],
                'meta'   => [],
                'errors' => null,
            ],
            $response->toArray(),
        );
    }
}
