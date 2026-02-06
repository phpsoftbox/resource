<?php

declare(strict_types=1);

namespace PhpSoftBox\Resource\Tests\Fixtures;

use function in_array;

final class RelationStateValue
{
    /** @param list<string> $loadedRelations */
    public function __construct(
        public mixed $role = null,
        public ?int $posts_count = null,
        public ?bool $posts_exists = null,
        public int|float|null $posts_likes_sum = null,
        private array $loadedRelations = [],
    ) {
    }

    public function isRelationLoaded(string $relation): bool
    {
        return in_array($relation, $this->loadedRelations, true);
    }
}
