<?php

declare(strict_types=1);

namespace PhpSoftBox\Resource\Tests;

use PhpSoftBox\Resource\EnumOptions;
use PhpSoftBox\Resource\Tests\Fixtures\SimpleEnum;
use PhpSoftBox\Resource\Tests\Fixtures\StatusEnum;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(EnumOptions::class)]
#[CoversMethod(EnumOptions::class, 'dropdown')]
final class EnumOptionsTest extends TestCase
{
    /**
     * Проверяет, что dropdown исключает кейсы и использует кастомные лейблы.
     *
     * @see EnumOptions::dropdown()
     * @see StatusEnum::exceptCasesFromDropdown()
     * @see StatusEnum::getLabel()
     */
    #[Test]
    public function dropdownSkipsExcludedCasesAndUsesLabels(): void
    {
        // DRAFT исключается из списка.
        $options = StatusEnum::dropdown();

        $this->assertSame([
            ['value' => 'active', 'label' => 'Активен'],
            ['value' => 'inactive', 'label' => 'Неактивен'],
        ], $options);
    }

    /**
     * Проверяет, что getCaseName и getCaseId возвращают имя в lower-case.
     *
     * @see EnumOptions::getCaseName()
     * @see EnumOptions::getCaseId()
     */
    #[Test]
    public function getCaseNameAndIdReturnLowercase(): void
    {
        $case = StatusEnum::ACTIVE;

        $this->assertSame('active', $case->getCaseName());
        $this->assertSame('active', $case->getCaseId());
    }

    /**
     * Проверяет, что getCaseIds и getCaseValues нормализуют значения.
     *
     * @see EnumOptions::getCaseIds()
     * @see EnumOptions::getCaseValues()
     */
    #[Test]
    public function getCaseIdsAndValuesAreNormalized(): void
    {
        $this->assertSame(['first', 'second'], SimpleEnum::getCaseIds());
        $this->assertSame(['first', 'second'], SimpleEnum::getCaseValues());
    }
}
