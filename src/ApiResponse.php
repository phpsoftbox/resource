<?php

declare(strict_types=1);

namespace PhpSoftBox\Resource;

use JsonSerializable;

use function array_is_list;
use function array_replace;
use function array_values;
use function interface_exists;
use function is_array;

final class ApiResponse implements JsonSerializable
{
    /**
     * @var array<string, mixed>
     */
    private array $meta;

    public function __construct(
        private mixed $data = null,
        array $meta = [],
        private ?ErrorBag $errors = null,
    ) {
        $this->meta = $meta;
        $this->data = $this->normalizeValue($this->data, false);
    }

    /**
     * Создаёт успешный ответ.
     *
     * @param array<string, mixed> $meta
     */
    public static function success(mixed $data = null, array $meta = []): self
    {
        return new self($data, $meta);
    }

    /**
     * Создаёт ответ с ошибками.
     *
     * @param array<string, list<string>|string> $fields
     * @param array<string, mixed> $meta
     */
    public static function error(
        string $message,
        array $fields = [],
        array $meta = [],
        ?string $code = null,
    ): self {
        return new self(null, $meta, new ErrorBag($message, $fields, $code));
    }

    /**
     * Заменяет данные ответа.
     */
    public function withData(mixed $data): self
    {
        return new self($data, $this->meta, $this->errors);
    }

    /**
     * Заменяет мета-данные ответа.
     *
     * @param array<string, mixed> $meta
     */
    public function withMeta(array $meta): self
    {
        return new self($this->data, $meta, $this->errors);
    }

    /**
     * Добавляет мета-данные ответа.
     *
     * @param array<string, mixed> $meta
     */
    public function mergeMeta(array $meta): self
    {
        return new self($this->data, array_replace($this->meta, $meta), $this->errors);
    }

    /**
     * Заменяет ошибки ответа.
     */
    public function withErrors(?ErrorBag $errors): self
    {
        return new self($this->data, $this->meta, $errors);
    }

    public function data(): mixed
    {
        return $this->data;
    }

    /**
     * @return array<string, mixed>
     */
    public function meta(): array
    {
        return $this->meta;
    }

    public function errors(): ?ErrorBag
    {
        return $this->errors;
    }

    /**
     * @return array{data:mixed,meta:array<string, mixed>,errors:array<string, mixed>|null}
     */
    public function toArray(): array
    {
        return [
            'data'   => $this->data,
            'meta'   => $this->meta,
            'errors' => $this->errors?->toArray(),
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    private function normalizeValue(mixed $value, bool $wrapResource): mixed
    {
        $paginationInterface = 'PhpSoftBox\\Pagination\\Contracts\\PaginationResultInterface';

        if ($value instanceof MissingValue) {
            return $value;
        }

        if (interface_exists($paginationInterface) && $value instanceof $paginationInterface) {
            return $value->toArray();
        }

        if ($value instanceof ResourceInterface) {
            $payload = $this->normalizeValue($value->toArray(), true);

            if (!$wrapResource) {
                $this->meta = array_replace($value->meta(), $this->meta);

                return $payload;
            }

            $wrapper = $value->wrapper();
            if ($wrapper === null || $wrapper === '') {
                return $payload;
            }

            return [$wrapper => $payload];
        }

        if (is_array($value)) {
            $isList = array_is_list($value);
            foreach ($value as $key => $item) {
                $value[$key] = $this->normalizeValue($item, true);

                if ($value[$key] instanceof MissingValue) {
                    unset($value[$key]);
                }
            }

            if ($isList) {
                return array_values($value);
            }

            return $value;
        }

        return $value;
    }
}
