<?php

declare(strict_types=1);

namespace PhpSoftBox\Resource\Tests\Fixtures;

use PhpSoftBox\Resource\Resource;

final class ApiResponseUserResource extends Resource
{
    public function toArray(): array
    {
        return [
            'id' => $this->resource['id'],
        ];
    }

    public function meta(): array
    {
        return ['source' => 'resource'];
    }
}
