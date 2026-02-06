<?php

declare(strict_types=1);

namespace PhpSoftBox\Resource\Tests;

use InvalidArgumentException;
use PhpSoftBox\Resource\EnumDropdownSource;
use PhpSoftBox\Resource\Tests\Fixtures\InvalidDropdownFixture;
use PhpSoftBox\Resource\Tests\Fixtures\MissingDropdownFixture;
use PhpSoftBox\Resource\Tests\Fixtures\StatusEnum;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function sprintf;

#[CoversClass(EnumDropdownSource::class)]
final class EnumDropdownSourceTest extends TestCase
{
    #[Test]
    public function dropdownReturnsOptionsFromStaticDropdownMethod(): void
    {
        $source = new EnumDropdownSource(StatusEnum::class);

        self::assertSame([
            ['value' => 'active', 'label' => 'Активен'],
            ['value' => 'inactive', 'label' => 'Неактивен'],
        ], $source->dropdown());
    }

    #[Test]
    public function constructorRejectsClassWithoutStaticDropdownMethod(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(sprintf(
            'Class "%s" must have static dropdown() method.',
            MissingDropdownFixture::class,
        ));

        new EnumDropdownSource(MissingDropdownFixture::class);
    }

    #[Test]
    public function dropdownReturnsEmptyArrayWhenStaticDropdownDoesNotReturnArray(): void
    {
        $source = new EnumDropdownSource(InvalidDropdownFixture::class);

        self::assertSame([], $source->dropdown());
    }
}
