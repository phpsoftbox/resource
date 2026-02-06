<?php

declare(strict_types=1);

namespace PhpSoftBox\Resource;

use BackedEnum;

use function in_array;
use function is_string;
use function mb_strtolower;
use function method_exists;
use function strtolower;

/**
 * @mixin BackedEnum
 */
trait EnumOptions
{
    /**
     * Возвращает массив кейсов, которые нужно исключить из выпадающего списка.
     */
    public static function exceptCasesFromDropdown(): array
    {
        return [];
    }

    /**
     * Возвращает массив для выпадающего списка, например:
     * [['value' => 'active', 'label' => 'Активен'], ['value' => 'inactive', 'label' => 'Неактивен']].
     *
     * @return array<int, array{value: string|int, label: string}>
     */
    public static function dropdown(): array
    {
        $preparedCases = [];
        foreach (self::cases() as $case) {
            if (in_array($case, self::exceptCasesFromDropdown(), true)) {
                continue;
            }

            $value = is_string($case->value) ? strtolower($case->value) : $case->value;

            if (method_exists(self::class, 'getLabel')) {
                $name = $case->getLabel();
            } else {
                $name = strtolower($case->name);
            }

            $preparedCases[] = [
                'value' => $value,
                'label' => $name,
            ];
        }

        return $preparedCases;
    }

    /**
     * Возвращает массив для выпадающего списка (алиас dropdown()).
     *
     * @return array<int, array{value: string|int, label: string}>
     */
    public static function getDropdown(): array
    {
        return self::dropdown();
    }

    /**
     * Возвращает имя кейса в виде строки, например: 'active', 'inactive', 'pending'.
     */
    public function getCaseName(): string
    {
        return mb_strtolower($this->name);
    }

    /**
     * Возвращает идентификатор кейса в виде строки (алиас getCaseName()).
     */
    public function getCaseId(): string
    {
        return $this->getCaseName();
    }

    /**
     * Возвращает имена всех кейсов в виде массива строк, например: ['active', 'inactive', 'pending'].
     *
     * @return array<int, string>
     */
    public static function getCaseNames(): array
    {
        $ids = [];
        foreach (self::cases() as $case) {
            $ids[] = strtolower($case->name);
        }

        return $ids;
    }

    /**
     * Возвращает идентификаторы всех кейсов (алиас getCaseNames()).
     *
     * @return array<int, string>
     */
    public static function getCaseIds(): array
    {
        return self::getCaseNames();
    }

    /**
     * Возвращает значения всех кейсов в виде массива, например:
     * ['active', 'inactive', 'pending'] или [1, 2, 3].
     *
     * @return array<int, string|int>
     */
    public static function getCaseValues(): array
    {
        $values = [];
        foreach (self::cases() as $case) {
            $values[] = is_string($case->value) ? strtolower($case->value) : $case->value;
        }

        return $values;
    }
}
