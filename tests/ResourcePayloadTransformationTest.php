<?php

declare(strict_types=1);

namespace PhpSoftBox\Resource\Tests;

use LogicException;
use PhpSoftBox\Resource\Resource;
use PhpSoftBox\Resource\ResourceInterface;
use PhpSoftBox\Resource\ResourcePayloadTransformation;
use PhpSoftBox\Resource\ResourcePayloadTransformerInterface;
use PhpSoftBox\Resource\Tests\Fixtures\FieldUserResource;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Resource::class)]
#[CoversClass(ResourcePayloadTransformation::class)]
final class ResourcePayloadTransformationTest extends TestCase
{
    /**
     * Проверяет, что through() применяет callable к сериализованному payload.
     */
    #[Test]
    public function appliesCallableTransformerToPayload(): void
    {
        $resource = new FieldUserResource($this->user())
            ->through(static function (array $payload): array {
                $payload['display_name'] = $payload['name'];

                return $payload;
            });

        self::assertSame([
            'id'           => 1,
            'name'         => 'Anton',
            'email'        => 'anton@example.test',
            'secret'       => 'hidden',
            'display_name' => 'Anton',
        ], $resource->toArray());
    }

    /**
     * Проверяет, что through() можно использовать после only()/except().
     */
    #[Test]
    public function appliesTransformerAfterFieldSelection(): void
    {
        $resource = new FieldUserResource($this->user())
            ->only('id', 'name')
            ->through(static function (array $payload): array {
                $payload['label'] = '#' . $payload['id'] . ' ' . $payload['name'];

                return $payload;
            });

        self::assertSame([
            'id'    => 1,
            'name'  => 'Anton',
            'label' => '#1 Anton',
        ], $resource->toArray());
    }

    /**
     * Проверяет, что transformer-interface получает исходный ResourceInterface.
     */
    #[Test]
    public function transformerInterfaceReceivesInnerResource(): void
    {
        $inner       = new FieldUserResource($this->user());
        $transformer = new class () implements ResourcePayloadTransformerInterface {
            public ?ResourceInterface $resource = null;

            public function transform(array $payload, ResourceInterface $resource): array
            {
                $this->resource            = $resource;
                $payload['resource_class'] = $resource::class;

                return $payload;
            }
        };

        $resource = $inner->through($transformer);

        self::assertSame(FieldUserResource::class, $resource->toArray()['resource_class']);
        self::assertSame($inner, $transformer->resource);
    }

    /**
     * Проверяет, что декоратор сохраняет wrapper и meta внутреннего ресурса.
     */
    #[Test]
    public function keepsInnerWrapperAndMeta(): void
    {
        $inner = new class (['id' => 10]) extends Resource {
            protected ?string $wrapper = 'user';

            public function meta(): array
            {
                return ['request_id' => 'req-1'];
            }

            public function toArray(): array
            {
                return ['id' => $this->resource['id']];
            }
        };

        $resource = $inner->through(static fn (array $payload): array => $payload + ['ready' => true]);

        self::assertSame('user', $resource->wrapper());
        self::assertSame(['request_id' => 'req-1'], $resource->meta());
        self::assertSame(['id' => 10, 'ready' => true], $resource->toArray());
    }

    /**
     * Проверяет понятную ошибку, если callable transformer возвращает не массив.
     */
    #[Test]
    public function throwsWhenCallableTransformerDoesNotReturnArray(): void
    {
        $resource = new FieldUserResource($this->user());

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Resource payload transformer must return array payload, got string.');

        $resource
            ->through(static fn (array $payload): string => 'invalid')
            ->toArray();
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
}
