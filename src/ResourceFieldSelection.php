<?php

declare(strict_types=1);

namespace PhpSoftBox\Resource;

final class ResourceFieldSelection extends Resource implements ResourceDecoratorInterface
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
        $payload = $this->inner instanceof Resource
            ? $this->inner->toArrayWithContext(new ResourceSerializationContext())
            : $this->inner->toArray();
        $payload = $this->discardExcludedDeferredValues($payload);
        $payload = $this->decorate($payload);

        foreach ($payload as $key => $value) {
            if ($value instanceof DeferredRequiredResourceValue) {
                $payload[$key] = $value->resolve();
            }
        }

        return $payload;
    }

    public function inner(): ResourceInterface
    {
        return $this->inner;
    }

    public function decorate(array $payload): array
    {
        return ResourceFieldFilter::apply(
            $payload,
            $this->onlyFields,
            $this->exceptFields,
        );
    }

    /**
     * Удаляет только deferred require* значения. Обычные поля остаются доступны
     * зарегистрированным и explicit transformers до штатного decorate().
     *
     * @param array<string|int, mixed> $payload
     * @return array<string|int, mixed>
     */
    public function discardExcludedDeferredValues(array $payload): array
    {
        foreach ($payload as $field => $value) {
            if (
                $value instanceof DeferredRequiredResourceValue
                && !ResourceFieldFilter::keeps($field, $this->onlyFields, $this->exceptFields)
            ) {
                unset($payload[$field]);
            }
        }

        return $payload;
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
        return clone($this, [
            'onlyFields' => ResourceFieldFilter::normalize($fields),
        ]);
    }

    /**
     * @param string|int|array<string|int> ...$fields
     */
    public function except(string|int|array ...$fields): self
    {
        return clone($this, [
            'exceptFields' => ResourceFieldFilter::merge(
                $this->exceptFields,
                ResourceFieldFilter::normalize($fields),
            ),
        ]);
    }
}
