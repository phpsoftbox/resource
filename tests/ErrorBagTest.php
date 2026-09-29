<?php

declare(strict_types=1);

namespace PhpSoftBox\Resource\Tests;

use PhpSoftBox\Resource\ErrorBag;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function json_encode;

use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_UNICODE;

#[CoversClass(ErrorBag::class)]
final class ErrorBagTest extends TestCase
{
    /**
     * Проверяет доступ к ошибкам по полям.
     */
    #[Test]
    public function itStoresFieldErrors(): void
    {
        $bag = new ErrorBag(
            message: 'Данные не прошли валидацию.',
            fields: [
                'email' => ['Некорректный email.'],
                'name'  => 'Поле name обязательно.',
            ],
            code: 'validation',
        );

        self::assertTrue($bag->has('email'));
        self::assertSame(['Некорректный email.'], $bag->get('email'));
        self::assertSame(['Поле name обязательно.'], $bag->get('name'));
        self::assertSame(
            [
                'email' => ['Некорректный email.'],
                'name'  => ['Поле name обязательно.'],
            ],
            $bag->all(),
        );
    }

    /**
     * Проверяет, что ErrorBag без ошибок по полям сериализуется в JSON с `fields` как объектом `{}`.
     *
     * @see ErrorBag::jsonSerialize()
     */
    #[Test]
    public function jsonEncodeOutputsEmptyFieldsAsObject(): void
    {
        $bag = new ErrorBag('Ошибка.', code: 'failed');

        self::assertSame(
            '{"message":"Ошибка.","fields":{},"code":"failed"}',
            json_encode($bag, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
        );
    }
}
