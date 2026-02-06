<?php

declare(strict_types=1);

namespace PhpSoftBox\Resource\Tests\Fixtures;

use PhpSoftBox\Resource\Resource;

final class PivotAwareResource extends Resource
{
    public function toArray(): array
    {
        return [
            'name'  => $this->name,
            'pivot' => $this->whenPivotLoaded(static fn (object $pivot): array => [
                'createdDatetime' => $pivot->createdDatetime,
            ]),
            'membership' => $this->whenPivotLoadedAs('membership', static fn (object $pivot): array => [
                'expiresDatetime' => $pivot->expiresDatetime,
            ]),
        ];
    }
}
