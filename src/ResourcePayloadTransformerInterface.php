<?php

declare(strict_types=1);

namespace PhpSoftBox\Resource;

interface ResourcePayloadTransformerInterface
{
    /**
     * @param array<string|int, mixed> $payload
     * @return array<string|int, mixed>
     */
    public function transform(array $payload, ResourceInterface $resource): array;
}
