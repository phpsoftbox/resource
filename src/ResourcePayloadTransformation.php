<?php

declare(strict_types=1);

namespace PhpSoftBox\Resource;

use Closure;
use LogicException;
use ReflectionFunction;

use function get_debug_type;
use function is_array;

final class ResourcePayloadTransformation extends Resource
{
    /**
     * @param list<callable|ResourcePayloadTransformerInterface> $transformers
     */
    public function __construct(
        private readonly ResourceInterface $inner,
        private readonly array $transformers,
    ) {
        parent::__construct($inner);
        $this->wrapper = $inner->wrapper();
    }

    public function toArray(): array
    {
        $payload = $this->inner->toArray();

        foreach ($this->transformers as $transformer) {
            $payload = $this->applyTransformer($transformer, $payload);
        }

        return $payload;
    }

    public function meta(): array
    {
        return $this->inner->meta();
    }

    /**
     * @param array<string|int, mixed> $payload
     * @return array<string|int, mixed>
     */
    private function applyTransformer(callable|ResourcePayloadTransformerInterface $transformer, array $payload): array
    {
        if ($transformer instanceof ResourcePayloadTransformerInterface) {
            return $transformer->transform($payload, $this->inner);
        }

        $callable   = Closure::fromCallable($transformer);
        $reflection = new ReflectionFunction($callable);

        if ($reflection->isVariadic() || $reflection->getNumberOfParameters() >= 2) {
            $payload = $callable($payload, $this->inner);
        } else {
            $payload = $callable($payload);
        }

        if (!is_array($payload)) {
            throw new LogicException('Resource payload transformer must return array payload, got ' . get_debug_type($payload) . '.');
        }

        return $payload;
    }
}
