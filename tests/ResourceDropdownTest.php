<?php

declare(strict_types=1);

namespace PhpSoftBox\Resource\Tests;

use InvalidArgumentException;
use PhpSoftBox\Resource\DropdownAwareInterface;
use PhpSoftBox\Resource\Resource;
use PhpSoftBox\Resource\ResourceDropdownAdapter;
use PhpSoftBox\Resource\Tests\Fixtures\StatusEnum;
use PhpSoftBox\Resource\Tests\Fixtures\TestDropdownSource;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Resource::class)]
#[CoversMethod(Resource::class, 'dropdown')]
final class ResourceDropdownTest extends TestCase
{
    /**
     * Проверяет, что dropdown добавляет дефолтную опцию "Все".
     *
     * @see Resource::dropdown()
     * @see DropdownAwareInterface::dropdown()
     */
    #[Test]
    public function dropdownPrependsDefaultAllOption(): void
    {
        $source = new TestDropdownSource();

        // По умолчанию добавляется пустая опция.
        $options = Resource::dropdown($source);

        $this->assertSame('all', $options[0]['value']);
        $this->assertSame('Все', $options[0]['label']);
        $this->assertSame(1, $options[1]['value']);
        $this->assertSame('One', $options[1]['label']);
    }

    /**
     * Проверяет, что dropdown не добавляет пустую опцию при prependEmpty=false.
     *
     * @see Resource::dropdown()
     * @see DropdownAwareInterface::dropdown()
     */
    #[Test]
    public function dropdownSkipsPrependWhenDisabled(): void
    {
        $source = new TestDropdownSource();

        // Отключаем добавление пустой опции.
        $options = Resource::dropdown($source, false);

        $this->assertCount(2, $options);
        $this->assertSame(1, $options[0]['value']);
        $this->assertSame('One', $options[0]['label']);
    }

    /**
     * Проверяет, что dropdown использует кастомную пустую опцию.
     *
     * @see Resource::dropdown()
     * @see DropdownAwareInterface::dropdown()
     */
    #[Test]
    public function dropdownUsesCustomEmptyOption(): void
    {
        $source = new TestDropdownSource();

        $options = Resource::dropdown($source, ['value' => 0, 'label' => 'Любой']);

        $this->assertSame(0, $options[0]['value']);
        $this->assertSame('Любой', $options[0]['label']);
    }

    /**
     * Проверяет, что dropdown использует кастомную пустую опцию.
     *
     * @see Resource::dropdown()
     * @see DropdownAwareInterface::dropdown()
     */
    #[Test]
    public function resourceDropdownAdapterConvertsEnumDropdown(): void
    {
        $source = new ResourceDropdownAdapter(StatusEnum::dropdown());

        $options = Resource::dropdown($source, ['value' => 0, 'label' => 'Любой']);

        $this->assertSame(0, $options[0]['value']);
        $this->assertSame('Любой', $options[0]['label']);
        $this->assertSame('active', $options[1]['value']);
        $this->assertSame('Активен', $options[1]['label']);
        $this->assertSame('inactive', $options[2]['value']);
        $this->assertSame('Неактивен', $options[2]['label']);
    }

    /**
     * Проверяет, что dropdown выбрасывает исключение для некорректного источника.
     *
     * @see Resource::dropdown()
     */
    #[Test]
    public function dropdownThrowsOnInvalidSource(): void
    {
        $this->expectException(InvalidArgumentException::class);

        // Некорректный источник не реализует DropdownAwareInterface.
        Resource::dropdown('invalid');
    }
}
