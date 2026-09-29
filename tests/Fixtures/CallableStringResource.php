<?php

declare(strict_types=1);

namespace PhpSoftBox\Resource\Tests\Fixtures;

use PhpSoftBox\Resource\Resource;

use function strtoupper;

/**
 * Ресурс, который передаёт в when*-helpers значения из данных (в тестах — имена PHP-функций).
 */
final class CallableStringResource extends Resource
{
    public function toArray(): array
    {
        return [
            'status'   => $this->when(true, $this->status),
            'fallback' => $this->when(false, 'value', $this->status),
            'role'     => $this->whenLoaded('role', $this->status),
            'upper'    => $this->when(true, fn (): string => strtoupper((string) $this->status)),
        ];
    }
}
