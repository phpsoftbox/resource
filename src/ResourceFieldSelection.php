<?php

declare(strict_types=1);

namespace PhpSoftBox\Resource;

final class ResourceFieldSelection extends Resource
{
    /**
     * @var list<string>|null
     */
    private ?array $onlyFields = null;

    /**
     * @var list<string>
     */
    private array $exceptFields = [];

    public function __construct(
        private readonly ResourceInterface $inner,
    ) {
        parent::__construct($inner);
        $this->wrapper = $inner->wrapper();
    }

    public function toArray(): array
    {
        return ResourceFieldFilter::apply(
            $this->inner->toArray(),
            $this->onlyFields,
            $this->exceptFields,
        );
    }

    public function meta(): array
    {
        return $this->inner->meta();
    }

    /**
     * @param string|int|array<string|int> ...$fields
     */
    public function only(string|int|array ...$fields): self
    {
        $clone             = clone $this;
        $clone->onlyFields = ResourceFieldFilter::normalize($fields);

        return $clone;
    }

    /**
     * @param string|int|array<string|int> ...$fields
     */
    public function except(string|int|array ...$fields): self
    {
        $clone               = clone $this;
        $clone->exceptFields = ResourceFieldFilter::merge(
            $this->exceptFields,
            ResourceFieldFilter::normalize($fields),
        );

        return $clone;
    }
}
