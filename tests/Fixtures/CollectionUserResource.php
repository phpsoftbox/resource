<?php

declare(strict_types=1);

namespace PhpSoftBox\Resource\Tests\Fixtures;

use PhpSoftBox\Resource\Resource;

final class CollectionUserResource extends Resource
{
    public function toArray(): array
    {
        return ['id' => $this->resource['id']];
    }
}
