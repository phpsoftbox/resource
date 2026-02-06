<?php

declare(strict_types=1);

namespace PhpSoftBox\Resource;

interface ResourceDecoratorInterface extends ResourceInterface
{
    public function inner(): ResourceInterface;

    /**
     * @param array<string|int, mixed> $payload
     * @return array<string|int, mixed>
     */
    public function decorate(array $payload): array;
}
