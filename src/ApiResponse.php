<?php

declare(strict_types=1);

namespace PhpSoftBox\Resource;

use JsonSerializable;

use function array_replace;

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
        private ?ResourceSerializerInterface $serializer = null,
    ) {
        $this->meta = $meta;
    }

    /**
     * Создаёт успешный ответ.
     *
     * @param array<string, mixed> $meta
     */
    public static function success(
        mixed $data = null,
        array $meta = [],
        ?ResourceSerializerInterface $serializer = null,
    ): self {
        return new self($data, $meta, serializer: $serializer);
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
        ?ResourceSerializerInterface $serializer = null,
    ): self {
        return new self(null, $meta, new ErrorBag($message, $fields, $code), $serializer);
    }

    /**
     * Заменяет данные ответа.
     */
    public function withData(mixed $data): self
    {
        return new self($data, $this->meta, $this->errors, $this->serializer);
    }

    /**
     * Заменяет мета-данные ответа.
     *
     * @param array<string, mixed> $meta
     */
    public function withMeta(array $meta): self
    {
        return new self($this->data, $meta, $this->errors, $this->serializer);
    }

    /**
     * Добавляет мета-данные ответа.
     *
     * @param array<string, mixed> $meta
     */
    public function mergeMeta(array $meta): self
    {
        return new self($this->data, array_replace($this->meta, $meta), $this->errors, $this->serializer);
    }

    /**
     * Заменяет ошибки ответа.
     */
    public function withErrors(?ErrorBag $errors): self
    {
        return new self($this->data, $this->meta, $errors, $this->serializer);
    }

    /**
     * Возвращает копию ответа с serializer для финальной нормализации payload.
     */
    public function withSerializer(ResourceSerializerInterface $serializer): self
    {
        return new self($this->data, $this->meta, $this->errors, $serializer);
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
        $serializer = $this->serializer ?? new ResourceSerializer();
        $meta       = $this->meta;

        if ($this->data instanceof ResourceInterface) {
            $meta = array_replace($this->data->meta(), $meta);
        }

        return [
            'data'   => $serializer->serialize($this->data),
            'meta'   => $serializer->serialize($meta),
            'errors' => $this->errors?->toArray(),
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

}
