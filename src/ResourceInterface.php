<?php

declare(strict_types=1);

namespace PhpSoftBox\Resource;

use JsonSerializable;

interface ResourceInterface extends JsonSerializable
{
    /**
     * Возвращает данные ресурса для сериализации.
     */
    public function toArray(): mixed;

    /**
     * Возвращает мета-данные ресурса.
     *
     * @return array<string, mixed>
     */
    public function meta(): array;

    /**
     * Возвращает название обёртки ресурса.
     */
    public function wrapper(): ?string;
}
