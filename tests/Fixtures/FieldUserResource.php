<?php

declare(strict_types=1);

namespace PhpSoftBox\Resource\Tests\Fixtures;

use PhpSoftBox\Resource\Resource;

final class FieldUserResource extends Resource
{
    public function toArray(): array
    {
        return [
            'id'     => $this->resource['id'],
            'name'   => $this->resource['name'],
            'email'  => $this->resource['email'],
            'secret' => $this->resource['secret'],
        ];
    }
}
