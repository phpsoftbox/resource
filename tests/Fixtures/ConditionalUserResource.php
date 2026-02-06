<?php

declare(strict_types=1);

namespace PhpSoftBox\Resource\Tests\Fixtures;

use PhpSoftBox\Resource\Resource;

final class ConditionalUserResource extends Resource
{
    public function toArray(): array
    {
        return [
            'name'       => $this->name,
            'secret'     => $this->when(false, 'hidden'),
            'role'       => $this->whenLoaded('role'),
            'postsCount' => $this->whenCounted('posts'),
        ];
    }
}
