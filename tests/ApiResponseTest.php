<?php

declare(strict_types=1);

namespace PhpSoftBox\Resource\Tests;

use PhpSoftBox\Resource\ApiResponse;
use PhpSoftBox\Resource\ErrorBag;
use PhpSoftBox\Resource\ResourceCollection;
use PhpSoftBox\Resource\ResourceInterface;
use PhpSoftBox\Resource\ResourcePayloadTransformerInterface;
use PhpSoftBox\Resource\ResourcePayloadTransformerRegistry;
use PhpSoftBox\Resource\ResourceSerializer;
use PhpSoftBox\Resource\Tests\Fixtures\ApiResponseUserResource;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function json_encode;

use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_UNICODE;

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
            ])->collects(ApiResponseUserResource::class)
                ->withMeta(['total' => 2]),
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
                        'meta' => ['total' => 2],
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
     * Проверяет отсутствие wrapper у одиночного вложенного ресурса по умолчанию.
     */
    #[Test]
    public function nestedSingleResourceHasNoDefaultWrapper(): void
    {
        $payload = [
            'user' => new ApiResponseUserResource(['id' => 1]),
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

    /**
     * Проверяет явный envelope одиночного ресурса вместе с его meta.
     */
    #[Test]
    public function nestedSingleResourceCanBeExplicitlyWrapped(): void
    {
        $response = ApiResponse::success([
            'user' => new ApiResponseUserResource(['id' => 1])->withWrapper('data'),
        ]);

        self::assertSame(
            [
                'data' => [
                    'user' => [
                        'data' => ['id' => 1],
                        'meta' => ['source' => 'resource'],
                    ],
                ],
                'meta'   => [],
                'errors' => null,
            ],
            $response->toArray(),
        );
    }

    /**
     * Проверяет отложенную сериализацию зарегистрированным serializer.
     */
    #[Test]
    public function registeredSerializerRunsOnlyAtFinalBoundary(): void
    {
        $registry = new ResourcePayloadTransformerRegistry();

        $registry->register(
            ApiResponseUserResource::class,
            new class () implements ResourcePayloadTransformerInterface {
                public function transform(array $payload, ResourceInterface $resource): array
                {
                    $payload['transformed'] = true;

                    return $payload;
                }
            },
        );

        $resource = new ApiResponseUserResource(['id' => 1]);

        $response = ApiResponse::success(
            $resource,
            serializer: new ResourceSerializer($registry),
        );

        self::assertSame($resource, $response->data());
        self::assertSame(
            ['id' => 1, 'transformed' => true],
            $response->toArray()['data'],
        );
    }

    /**
     * Проверяет, что пустая meta в JSON выводится объектом `{}`, а не массивом `[]`.
     *
     * @see ApiResponse::jsonSerialize()
     */
    #[Test]
    public function jsonEncodeOutputsEmptyMetaAsObject(): void
    {
        $response = ApiResponse::success(['id' => 1]);

        self::assertSame('{"data":{"id":1},"meta":{},"errors":null}', json_encode($response, JSON_THROW_ON_ERROR));
    }

    /**
     * Проверяет, что пустые errors.fields в JSON выводятся объектом `{}`.
     *
     * @see ApiResponse::jsonSerialize()
     * @see ErrorBag::jsonSerialize()
     */
    #[Test]
    public function jsonEncodeOutputsEmptyErrorFieldsAsObject(): void
    {
        $response = ApiResponse::error('Ошибка.');

        self::assertSame(
            '{"data":null,"meta":{},"errors":{"message":"Ошибка.","fields":{}}}',
            json_encode($response, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
        );
    }

    /**
     * Проверяет, что непустые meta и errors.fields в JSON тоже остаются объектами.
     *
     * @see ApiResponse::jsonSerialize()
     */
    #[Test]
    public function jsonEncodeOutputsFilledMetaAndErrorFieldsAsObjects(): void
    {
        $response = ApiResponse::error('Ошибка.', ['email' => 'Неверно.'], ['trace_id' => 'abc']);

        self::assertSame(
            '{"data":null,"meta":{"trace_id":"abc"},"errors":{"message":"Ошибка.","fields":{"email":["Неверно."]}}}',
            json_encode($response, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
        );
    }
}
