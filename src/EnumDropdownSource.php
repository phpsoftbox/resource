<?php

declare(strict_types=1);

namespace PhpSoftBox\Resource;

use InvalidArgumentException;

use function is_array;
use function method_exists;
use function sprintf;

/**
 * @template T of object
 */
final readonly class EnumDropdownSource implements DropdownAwareInterface
{
    /**
     * @param class-string<T> $className
     */
    public function __construct(
        private string $className,
    ) {
        if (!method_exists($this->className, 'dropdown')) {
            throw new InvalidArgumentException(
                sprintf('Class "%s" must have static dropdown() method.', $this->className),
            );
        }
    }

    /**
     * @return array<int, array{value: string|int|null, label: string, meta?: array<string, mixed>}>
     */
    public function dropdown(): array
    {
        $options = $this->className::dropdown();

        return is_array($options) ? $options : [];
    }
}
