<?php

declare(strict_types=1);

namespace PhpSoftBox\Resource\Tests;

use PhpSoftBox\Resource\Resource;
use PhpSoftBox\Resource\ResourceSerializer;
use PhpSoftBox\Resource\Tests\Fixtures\CallableStringResource;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Resource::class)]
#[CoversMethod(Resource::class, 'when')]
#[CoversMethod(Resource::class, 'whenLoaded')]
final class ResourceCallableValueTest extends TestCase
{
    /**
     * Проверяет, что строковое значение из данных, совпадающее с именем функции ("count"),
     * возвращается как есть и не вызывается в when().
     *
     * @see Resource::when()
     */
    #[Test]
    public function whenDoesNotCallCallableStringValue(): void
    {
        $payload = $this->serialize();

        self::assertSame('count', $payload['status'] ?? null);
    }

    /**
     * Проверяет, что строковое значение по умолчанию в when() не вызывается как функция.
     *
     * @see Resource::when()
     */
    #[Test]
    public function whenDoesNotCallCallableStringDefault(): void
    {
        $payload = $this->serialize();

        self::assertSame('count', $payload['fallback'] ?? null);
    }

    /**
     * Проверяет, что whenLoaded() не вызывает строку "count" с загруженным отношением в качестве аргумента.
     *
     * @see Resource::whenLoaded()
     */
    #[Test]
    public function whenLoadedDoesNotCallCallableStringValue(): void
    {
        $payload = $this->serialize();

        self::assertSame('count', $payload['role'] ?? null);
    }

    /**
     * Проверяет, что Closure по-прежнему вызывается для вычисления значения.
     *
     * @see Resource::when()
     */
    #[Test]
    public function whenCallsClosureValue(): void
    {
        $payload = $this->serialize();

        self::assertSame('COUNT', $payload['upper'] ?? null);
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(): array
    {
        $resource = new CallableStringResource([
            'status' => 'count',
            'role'   => ['admin', 'manager'],
        ]);

        return new ResourceSerializer()->serialize($resource);
    }
}
