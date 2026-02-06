<?php

declare(strict_types=1);

namespace PhpSoftBox\Resource\Tests\Fixtures;

use PhpSoftBox\Resource\Resource;

final class RequiredConditionalResource extends Resource
{
    public function toArray(): array
    {
        return [
            'role'          => $this->requireLoaded('role'),
            'postsCount'    => $this->requireCounted('posts'),
            'postsExists'   => $this->requireExists('posts'),
            'postsLikesSum' => $this->requireSum('posts', 'likes'),
        ];
    }
}
