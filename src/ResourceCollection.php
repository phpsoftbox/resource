<?php

declare(strict_types=1);

namespace PhpSoftBox\Resource;

use function array_is_list;
use function array_replace;
use function array_values;
use function interface_exists;
use function is_a;
use function is_array;
use function is_callable;
use function is_iterable;
use function is_object;
use function method_exists;

final class ResourceCollection extends Resource
{
    /**
     * @var callable|null
     */
    private $mapper;

    /**
     * @var array<string, mixed>
     */
    private array $meta = [];

    /**
     * @var array<string, mixed>|null
     */
    private ?array $paginationLinks = null;

    /**
     * @var array<string, mixed>|null
     */
    private ?array $paginationMeta = null;

    /**
     * @param iterable<mixed> $resource
     */
    public function __construct(iterable $resource, ?callable $mapper = null)
    {
        parent::__construct($resource);
        $this->mapper = $mapper;
    }

    /**
     * Создаёт коллекцию из результата пагинации.
     *
     * @param array{items?: iterable<mixed>, total?: int, page?: int, perPage?: int, pages?: int}|object $paginator
     */
    public static function fromPaginator(array|object $paginator, ?callable $mapper = null): self
    {
        $paginationInterface = 'PhpSoftBox\\Pagination\\Contracts\\PaginationResultInterface';

        if (interface_exists($paginationInterface) && $paginator instanceof $paginationInterface) {
            $payload = $paginator->toArray();
            $items   = $payload['data'] ?? [];

            if (!is_iterable($items)) {
                $items = [];
            }

            $collection = new self($items, $mapper);

            return $collection->withPagination(
                (array) ($payload['links'] ?? []),
                (array) ($payload['meta'] ?? []),
            );
        }

        $items = $paginator['items'] ?? [];

        if (!is_iterable($items)) {
            $items = [];
        }

        $collection = new self($items, $mapper);
        $meta       = [
            'total'   => (int) ($paginator['total'] ?? 0),
            'page'    => (int) ($paginator['page'] ?? 1),
            'perPage' => (int) ($paginator['perPage'] ?? 0),
            'pages'   => (int) ($paginator['pages'] ?? 1),
        ];

        return $collection->withMeta($meta);
    }

    /**
     * Указывает трансформер для элементов коллекции.
     */
    public function map(callable $mapper): self
    {
        $clone         = clone $this;
        $clone->mapper = $mapper;

        return $clone;
    }

    /**
     * Указывает класс ресурса для каждого элемента коллекции.
     *
     * @param class-string<ResourceInterface> $resourceClass
     */
    public function collects(string $resourceClass): self
    {
        if (!is_a($resourceClass, ResourceInterface::class, true)) {
            throw new InvalidArgumentException('Класс ресурса должен реализовывать ResourceInterface.');
        }

        return $this->map(static fn (mixed $item): ResourceInterface => new $resourceClass($item));
    }

    /**
     * Заменяет мета-данные коллекции.
     *
     * @param array<string, mixed> $meta
     */
    public function withMeta(array $meta): self
    {
        $clone       = clone $this;
        $clone->meta = $meta;

        return $clone;
    }

    /**
     * Добавляет мета-данные коллекции.
     *
     * @param array<string, mixed> $meta
     */
    public function mergeMeta(array $meta): self
    {
        $clone       = clone $this;
        $clone->meta = array_replace($this->meta, $meta);

        return $clone;
    }

    public function meta(): array
    {
        if ($this->paginationMeta !== null) {
            return array_replace($this->paginationMeta, $this->meta);
        }

        return $this->meta;
    }

    public function toArray(): array
    {
        if (!is_iterable($this->resource)) {
            return [];
        }

        $items = [];
        foreach ($this->resource as $item) {
            $mapped = $this->mapItem($item);

            if ($mapped instanceof MissingValue) {
                continue;
            }

            if (is_array($mapped)) {
                $mapped = $this->filterMissing($mapped);
            }

            $items[] = $mapped;
        }

        if ($this->paginationLinks !== null || $this->paginationMeta !== null) {
            return [
                'data'  => $items,
                'links' => $this->paginationLinks ?? [],
                'meta'  => $this->meta(),
            ];
        }

        return $items;
    }

    private function mapItem(mixed $item): mixed
    {
        if (is_callable($this->mapper)) {
            $mapped = ($this->mapper)($item);

            return $this->normalizeItem($mapped);
        }

        return $this->normalizeItem($item);
    }

    private function normalizeItem(mixed $item): mixed
    {
        if ($item instanceof ResourceInterface) {
            return $item->toArray();
        }

        if (is_object($item) && method_exists($item, 'toArray')) {
            return $item->toArray();
        }

        return $item;
    }

    /**
     * @param array<string, mixed> $links
     * @param array<string, mixed> $meta
     */
    public function withPagination(array $links, array $meta): self
    {
        $clone                  = clone $this;
        $clone->paginationLinks = $links;
        $clone->paginationMeta  = $meta;
        $clone->wrapper         = null;

        return $clone;
    }

    private function filterMissing(mixed $value): mixed
    {
        if (is_array($value)) {
            $isList = array_is_list($value);
            foreach ($value as $key => $item) {
                if ($item instanceof MissingValue) {
                    unset($value[$key]);
                    continue;
                }

                if (is_array($item)) {
                    $value[$key] = $this->filterMissing($item);
                }
            }

            return $isList ? array_values($value) : $value;
        }

        return $value;
    }
}
