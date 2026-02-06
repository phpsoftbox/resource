<?php

declare(strict_types=1);

namespace PhpSoftBox\Resource;

use ArrayAccess;
use InvalidArgumentException;
use ReflectionObject;

use function array_key_exists;
use function array_merge;
use function array_values;
use function func_num_args;
use function interface_exists;
use function is_array;
use function is_callable;
use function is_iterable;
use function is_object;
use function method_exists;
use function preg_replace;
use function strtolower;

abstract class Resource implements ResourceInterface
{
    /**
     * @param mixed $resource Исходный ресурс.
     */
    public function __construct(
        protected mixed $resource,
    ) {
    }

    /**
     * Обёртка ресурса внутри массива данных.
     */
    protected ?string $wrapper = 'data';

    public function jsonSerialize(): mixed
    {
        if ($this->resource === null) {
            return null;
        }

        return $this->toArray();
    }

    public function meta(): array
    {
        return [];
    }

    public function wrapper(): ?string
    {
        return $this->wrapper;
    }

    /**
     * Возвращает исходный ресурс.
     */
    public function resource(): mixed
    {
        return $this->resource;
    }

    /**
     * Устанавливает обёртку для ресурса.
     */
    public function withWrapper(?string $wrapper): static
    {
        $clone          = clone $this;
        $clone->wrapper = $wrapper;

        return $clone;
    }

    /**
     * Отключает обёртку для ресурса.
     */
    public function withoutWrapper(): static
    {
        return $this->withWrapper(null);
    }

    public function __get(string $key): mixed
    {
        if (is_array($this->resource) && array_key_exists($key, $this->resource)) {
            return $this->resource[$key];
        }

        if (is_object($this->resource)) {
            if (method_exists($this->resource, '__get')) {
                return $this->resource->{$key};
            }

            $reflection = new ReflectionObject($this->resource);

            if ($reflection->hasProperty($key)) {
                $property = $reflection->getProperty($key);
                if ($property->isPublic()) {
                    return $this->resource->{$key};
                }
            }
        }

        return null;
    }

    /**
     * Возвращает значение при истинном условии.
     */
    protected function when(bool|callable $condition, mixed $value, mixed $default = null): mixed
    {
        $defaultValue = func_num_args() === 3 ? $default : new MissingValue();
        $result       = is_callable($condition) ? $condition($this->resource) : $condition;

        if ($result) {
            return $this->resolveValue($value, $this->resource);
        }

        return $this->resolveValue($defaultValue, $this->resource);
    }

    /**
     * Возвращает значение, если отношение было загружено.
     */
    protected function whenLoaded(
        string $relation,
        mixed $value = null,
        mixed $default = null,
    ): mixed {
        $defaultValue = func_num_args() === 3 ? $default : new MissingValue();

        if (!$this->hasAttribute($relation)) {
            return $this->resolveValue($defaultValue, $this->resource);
        }

        $relationValue = $this->getAttribute($relation);

        if (func_num_args() === 1) {
            return $relationValue;
        }

        if ($relationValue === null) {
            return null;
        }

        if ($value === null) {
            return $relationValue;
        }

        return $this->resolveValue($value, $relationValue, $this->resource);
    }

    /**
     * Возвращает значение, если был загружен счётчик отношения.
     */
    protected function whenCounted(
        string $relationship,
        mixed $value = null,
        mixed $default = null,
    ): mixed {
        $defaultValue = func_num_args() === 3 ? $default : new MissingValue();
        $attribute    = $this->snake($relationship) . '_count';

        if (!$this->hasAttribute($attribute)) {
            return $this->resolveValue($defaultValue, $this->resource);
        }

        $countValue = $this->getAttribute($attribute);

        if (func_num_args() === 1) {
            return $countValue;
        }

        if ($countValue === null) {
            return null;
        }

        if ($value === null) {
            return $countValue;
        }

        return $this->resolveValue($value, $countValue, $this->resource);
    }

    public function toArray(): mixed
    {
        return $this->resource;
    }

    /**
     * Возвращает опции для выпадающего списка.
     *
     * @param bool|array{value: string|int, label: string} $prependEmpty
     * @return array<int, array{value: string|int, label: string}>
     */
    public static function dropdown(DropdownAwareInterface|string $dropdown, bool|array $prependEmpty = true): array
    {
        if ($dropdown instanceof DropdownAwareInterface) {
            $options = $dropdown->dropdown();
        } else {
            throw new InvalidArgumentException('Dropdown source must implement DropdownAwareInterface or provide static dropdown().');
        }

        $options = array_values($options);

        if ($prependEmpty !== false) {
            $empty = $prependEmpty === true
                ? ['value' => 'all', 'label' => 'Все']
                : $prependEmpty;
            $options = array_merge([$empty], $options);
        }

        return $options;
    }

    /**
     * Создаёт коллекцию ресурсов.
     *
     * @param iterable<mixed>|object $items
     */
    public static function collection(iterable|object $items): ResourceCollection
    {
        $paginationInterface = 'PhpSoftBox\\Pagination\\Contracts\\PaginationResultInterface';

        if (interface_exists($paginationInterface) && $items instanceof $paginationInterface) {
            $payload = $items->toArray();
            $data    = $payload['data'] ?? [];

            if (!is_iterable($data)) {
                throw new InvalidArgumentException('PaginationResultInterface::data() должен быть iterable.');
            }

            return new ResourceCollection($data)
                ->withPagination(
                    (array) ($payload['links'] ?? []),
                    (array) ($payload['meta'] ?? []),
                )
                ->collects(static::class);
        }

        if (!is_iterable($items)) {
            throw new InvalidArgumentException('Коллекция должна быть iterable.');
        }

        return new ResourceCollection($items)->collects(static::class);
    }

    private function resolveValue(mixed $value, mixed ...$args): mixed
    {
        if (is_callable($value)) {
            return $value(...$args);
        }

        return $value;
    }

    private function hasAttribute(string $key): bool
    {
        if (is_array($this->resource)) {
            return array_key_exists($key, $this->resource);
        }

        if ($this->resource instanceof ArrayAccess) {
            return $this->resource->offsetExists($key);
        }

        if (is_object($this->resource)) {
            if (method_exists($this->resource, 'relationLoaded')) {
                return (bool) $this->resource->relationLoaded($key);
            }

            if (method_exists($this->resource, 'isRelationLoaded')) {
                return (bool) $this->resource->isRelationLoaded($key);
            }

            if (method_exists($this->resource, '__isset') && isset($this->resource->{$key})) {
                return true;
            }

            $reflection = new ReflectionObject($this->resource);

            if ($reflection->hasProperty($key)) {
                return $reflection->getProperty($key)->isPublic();
            }
        }

        return false;
    }

    private function getAttribute(string $key): mixed
    {
        if (is_array($this->resource)) {
            return $this->resource[$key] ?? null;
        }

        if ($this->resource instanceof ArrayAccess) {
            return $this->resource[$key] ?? null;
        }

        if (is_object($this->resource)) {
            if (method_exists($this->resource, '__get')) {
                return $this->resource->{$key};
            }

            $reflection = new ReflectionObject($this->resource);

            if ($reflection->hasProperty($key)) {
                $property = $reflection->getProperty($key);
                if ($property->isPublic()) {
                    return $this->resource->{$key};
                }
            }
        }

        return null;
    }

    private function snake(string $value): string
    {
        $snake = preg_replace('~(?<=\\w)([A-Z])~u', '_$1', $value);

        if ($snake === null) {
            return $value;
        }

        return strtolower($snake);
    }
}
