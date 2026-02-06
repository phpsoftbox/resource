<?php

declare(strict_types=1);

namespace PhpSoftBox\Resource;

use JsonSerializable;

use function array_key_exists;
use function is_array;
use function is_scalar;

final class ErrorBag implements JsonSerializable
{
    /**
     * @var array<string, list<string>>
     */
    private array $fields;

    /**
     * @param string $message Общее сообщение об ошибке.
     * @param array<string, list<string>|string> $fields Ошибки по полям.
     */
    public function __construct(
        private string $message,
        array $fields = [],
        private ?string $code = null,
    ) {
        $this->fields = $this->normalizeFields($fields);
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    public function message(): string
    {
        return $this->message;
    }

    public function code(): ?string
    {
        return $this->code;
    }

    public function hasErrors(): bool
    {
        return $this->message !== '' || $this->fields !== [];
    }

    public function has(string $field): bool
    {
        return array_key_exists($field, $this->fields);
    }

    /**
     * @return list<string>
     */
    public function get(string $field): array
    {
        return $this->fields[$field] ?? [];
    }

    /**
     * @return array<string, list<string>>
     */
    public function all(): array
    {
        return $this->fields;
    }

    /**
     * @return array{message:string,fields:array<string, list<string>>,code?:string}
     */
    public function toArray(): array
    {
        $out = [
            'message' => $this->message,
            'fields'  => $this->fields,
        ];

        if ($this->code !== null) {
            $out['code'] = $this->code;
        }

        return $out;
    }

    private function normalizeFields(array $fields): array
    {
        $normalized = [];

        foreach ($fields as $field => $messages) {
            if (!is_array($messages)) {
                $messages = [$messages];
            }

            $normalized[$field] = [];
            foreach ($messages as $message) {
                if (is_scalar($message)) {
                    $normalized[$field][] = (string) $message;
                } else {
                    $normalized[$field][] = '';
                }
            }
        }

        return $normalized;
    }
}
