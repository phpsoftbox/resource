<?php

declare(strict_types=1);

namespace PhpSoftBox\Resource;

use ArrayAccess;
use InvalidArgumentException;
use LogicException;
use PhpSoftBox\Collection\Collection;
use ReflectionObject;

use function array_key_exists;
use function func_num_args;
use function interface_exists;
use function is_array;
use function is_callable;
use function is_iterable;
use function is_object;
use function method_exists;
use function preg_replace;
use function strtolower;
use function trim;

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

    /**
     * Оставляет только указанные поля сериализованного ресурса.
     *
     * @param string|int|array<string|int> ...$fields
     */
    public function only(string|int|array ...$fields): self
    {
        return new ResourceFieldSelection($this)->only(...$fields);
    }

    /**
     * Исключает указанные поля из сериализованного ресурса.
     *
     * @param string|int|array<string|int> ...$fields
     */
    public function except(string|int|array ...$fields): self
    {
        return new ResourceFieldSelection($this)->except(...$fields);
    }

    /**
     * Применяет post-processing к уже сериализованному payload.
     */
    public function through(callable|ResourcePayloadTransformerInterface ...$transformers): self
    {
        if ($transformers === []) {
            return $this;
        }

        return new ResourcePayloadTransformation($this, $transformers);
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

    public function __isset(string $key): bool
    {
        return $this->hasAttribute($key);
    }

    public function __set(string $key, mixed $value): void
    {
        throw new LogicException('Resource property "' . $key . '" is read-only.');
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
     * Возвращает значение, если pivot был загружен на исходном ресурсе.
     */
    protected function whenPivotLoaded(mixed $value = null, mixed $default = null): mixed
    {
        return match (func_num_args()) {
            2       => $this->whenPivotLoadedAs('pivot', $value, $default),
            1       => $this->whenPivotLoadedAs('pivot', $value),
            default => $this->whenPivotLoadedAs('pivot'),
        };
    }

    /**
     * Возвращает значение, если pivot был загружен через указанный accessor.
     */
    protected function whenPivotLoadedAs(
        string $accessor,
        mixed $value = null,
        mixed $default = null,
    ): mixed {
        $argumentCount = func_num_args();
        $defaultValue  = $argumentCount === 3 ? $default : new MissingValue();
        $pivotValue    = $this->pivotValue($accessor);

        if ($pivotValue === null) {
            return $this->resolveValue($defaultValue, $this->resource);
        }

        if ($argumentCount === 1) {
            return $pivotValue;
        }

        if ($value === null) {
            return $pivotValue;
        }

        return $this->resolveValue($value, $pivotValue, $this->resource);
    }

    /**
     * Возвращает значение, если был загружен счётчик отношения.
     */
    protected function whenCounted(
        string $relationship,
        mixed $value = null,
        mixed $default = null,
    ): mixed {
        return $this->whenAttributeValue(
            attribute: $this->snake($relationship) . '_count',
            argumentCount: func_num_args(),
            baseArgumentCount: 1,
            value: $value,
            default: $default,
        );
    }

    /**
     * Возвращает значение, если был загружен флаг существования отношения.
     */
    protected function whenExists(
        string $relationship,
        mixed $value = null,
        mixed $default = null,
    ): mixed {
        return $this->whenAttributeValue(
            attribute: $this->snake($relationship) . '_exists',
            argumentCount: func_num_args(),
            baseArgumentCount: 1,
            value: $value,
            default: $default,
        );
    }

    /**
     * Возвращает значение, если был загружен агрегат отношения.
     */
    protected function whenAggregated(
        string $relationship,
        string $column,
        string $aggregate,
        mixed $value = null,
        mixed $default = null,
    ): mixed {
        return $this->whenAttributeValue(
            attribute: $this->aggregateAttribute($relationship, $column, $aggregate),
            argumentCount: func_num_args(),
            baseArgumentCount: 3,
            value: $value,
            default: $default,
        );
    }

    protected function whenSum(
        string $relationship,
        string $column,
        mixed $value = null,
        mixed $default = null,
    ): mixed {
        return match (func_num_args()) {
            4       => $this->whenAggregated($relationship, $column, 'sum', $value, $default),
            3       => $this->whenAggregated($relationship, $column, 'sum', $value),
            default => $this->whenAggregated($relationship, $column, 'sum'),
        };
    }

    protected function whenAvg(
        string $relationship,
        string $column,
        mixed $value = null,
        mixed $default = null,
    ): mixed {
        return match (func_num_args()) {
            4       => $this->whenAggregated($relationship, $column, 'avg', $value, $default),
            3       => $this->whenAggregated($relationship, $column, 'avg', $value),
            default => $this->whenAggregated($relationship, $column, 'avg'),
        };
    }

    protected function whenMin(
        string $relationship,
        string $column,
        mixed $value = null,
        mixed $default = null,
    ): mixed {
        return match (func_num_args()) {
            4       => $this->whenAggregated($relationship, $column, 'min', $value, $default),
            3       => $this->whenAggregated($relationship, $column, 'min', $value),
            default => $this->whenAggregated($relationship, $column, 'min'),
        };
    }

    protected function whenMax(
        string $relationship,
        string $column,
        mixed $value = null,
        mixed $default = null,
    ): mixed {
        return match (func_num_args()) {
            4       => $this->whenAggregated($relationship, $column, 'max', $value, $default),
            3       => $this->whenAggregated($relationship, $column, 'max', $value),
            default => $this->whenAggregated($relationship, $column, 'max'),
        };
    }

    abstract public function toArray(): array;

    /**
     * Возвращает опции для выпадающего списка.
     *
     * @param bool|array{value: string|int|null, label: string, meta?: array<string, mixed>} $prependEmpty
     * @return array<int, array{value: string|int|null, label: string, meta?: array<string, mixed>}>
     */
    public static function dropdown(DropdownAwareInterface|string $dropdown, bool|array $prependEmpty = true): array
    {
        if ($dropdown instanceof DropdownAwareInterface) {
            $adapter = new ResourceDropdownAdapter($dropdown->dropdown());
        } else {
            throw new InvalidArgumentException('Dropdown source must implement DropdownAwareInterface or provide static dropdown().');
        }

        if ($prependEmpty !== false) {
            $empty = $prependEmpty === true
                ? ['value' => 'all', 'label' => 'Все']
                : $prependEmpty;

            $adapter = $adapter->prepend($empty);
        }

        return Collection::from($adapter->dropdown())
            ->values()
            ->all();
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

    private function whenAttributeValue(
        string $attribute,
        int $argumentCount,
        int $baseArgumentCount,
        mixed $value = null,
        mixed $default = null,
    ): mixed {
        $defaultValue = $argumentCount === $baseArgumentCount + 2 ? $default : new MissingValue();

        if (!$this->hasAttribute($attribute)) {
            return $this->resolveValue($defaultValue, $this->resource);
        }

        $attributeValue = $this->getAttribute($attribute);

        if ($argumentCount === $baseArgumentCount) {
            return $attributeValue;
        }

        if ($attributeValue === null) {
            return null;
        }

        if ($value === null) {
            return $attributeValue;
        }

        return $this->resolveValue($value, $attributeValue, $this->resource);
    }

    private function aggregateAttribute(string $relationship, string $column, string $aggregate): string
    {
        $relationship = $this->snake($relationship);
        $column       = trim($column);
        $aggregate    = strtolower(trim($aggregate));

        if ($column === '' || $column === '*') {
            return $relationship . '_' . $aggregate;
        }

        return $relationship . '_' . $this->snake($column) . '_' . $aggregate;
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

    private function pivotValue(string $accessor): mixed
    {
        $accessor = trim($accessor);
        if ($accessor === '' || !is_object($this->resource) || !method_exists($this->resource, $accessor)) {
            return null;
        }

        return $this->resource->{$accessor}();
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
