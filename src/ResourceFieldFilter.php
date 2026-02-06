<?php

declare(strict_types=1);

namespace PhpSoftBox\Resource;

use function array_fill_keys;
use function array_key_exists;
use function array_merge;
use function array_values;
use function is_array;
use function is_int;
use function is_string;

final class ResourceFieldFilter
{
    /**
     * @param list<string> $only
     * @param list<string> $except
     */
    public static function apply(mixed $value, ?array $only = null, array $except = []): mixed
    {
        if (!is_array($value)) {
            return $value;
        }

        if ($only !== null) {
            $allowed = array_fill_keys($only, true);
            foreach ($value as $key => $_item) {
                if (!is_string($key) && !is_int($key)) {
                    continue;
                }

                if (!array_key_exists((string) $key, $allowed)) {
                    unset($value[$key]);
                }
            }
        }

        foreach ($except as $field) {
            unset($value[$field]);
        }

        return $value;
    }

    /**
     * @param list<string|int|array<string|int>> $fields
     * @return list<string>
     */
    public static function normalize(array $fields): array
    {
        $normalized = [];
        foreach ($fields as $field) {
            foreach (is_array($field) ? $field : [$field] as $item) {
                if (!is_string($item) && !is_int($item)) {
                    continue;
                }

                $item = (string) $item;
                if ($item !== '') {
                    $normalized[$item] = $item;
                }
            }
        }

        return array_values($normalized);
    }

    /**
     * @param list<string> $left
     * @param list<string> $right
     * @return list<string>
     */
    public static function merge(array $left, array $right): array
    {
        return self::normalize(array_merge($left, $right));
    }
}
