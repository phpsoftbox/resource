<?php

declare(strict_types=1);

namespace PhpSoftBox\Resource;

use PhpSoftBox\Collection\Collection;

use function array_key_exists;

final readonly class ResourceDropdownAdapter implements DropdownAwareInterface
{
    /**
     * @var array<int, array{value: string|int|null, label: string, meta?: array<string, mixed>}>
     */
    private array $dropdown;

    /**
     * @param array<int, array{value: string|int|null, label: string, meta?: array<string, mixed>}> $dropdown
     */
    public function __construct(array $dropdown)
    {
        $this->dropdown = Collection::from($dropdown)
            ->map(static fn (array $option): array => self::normalizeOption($option))
            ->values()
            ->all();
    }

    /**
     * @param array{value: string|int|null, label: string, meta?: array<string, mixed>} ...$options
     */
    public function prepend(array ...$options): self
    {
        if ($options === []) {
            return $this;
        }

        return new self(Collection::from($options)
            ->merge($this->dropdown, ['list' => 'append'])
            ->values()
            ->all());
    }

    /**
     * @param array{value: string|int|null, label: string, meta?: array<string, mixed>} ...$options
     */
    public function append(array ...$options): self
    {
        if ($options === []) {
            return $this;
        }

        return new self(Collection::from($this->dropdown)
            ->merge($options, ['list' => 'append'])
            ->values()
            ->all());
    }

    /**
     * @return array<int, array{value: string|int|null, label: string, meta?: array<string, mixed>}>
     */
    public function dropdown(): array
    {
        return Collection::from($this->dropdown)->values()->all();
    }

    /**
     * @param array{value: string|int|null, label: string, meta?: array<string, mixed>} $option
     * @return array{value: string|int|null, label: string, meta?: array<string, mixed>}
     */
    private static function normalizeOption(array $option): array
    {
        $normalized = [
            'value' => $option['value'],
            'label' => (string) $option['label'],
        ];

        if (array_key_exists('meta', $option)) {
            $normalized['meta'] = $option['meta'];
        }

        return Collection::from($normalized)->only(['value', 'label', 'meta'])->all();
    }
}
