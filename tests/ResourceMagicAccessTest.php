<?php

declare(strict_types=1);

namespace PhpSoftBox\Resource\Tests;

use LogicException;
use PhpSoftBox\Resource\Resource;
use PhpSoftBox\Resource\Tests\Fixtures\FieldUserResource;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Resource::class)]
final class ResourceMagicAccessTest extends TestCase
{
    /**
     * Проверяет, что isset() использует тот же read-only доступ к исходному ресурсу.
     */
    #[Test]
    public function issetChecksUnderlyingResourceAttribute(): void
    {
        $resource = new FieldUserResource([
            'id'     => 1,
            'name'   => 'Anton',
            'email'  => 'anton@example.test',
            'secret' => 'hidden',
        ]);

        self::assertTrue(isset($resource->name));
        self::assertFalse(isset($resource->missing));
    }

    /**
     * Проверяет, что Resource не становится mutable array-builder через magic set.
     */
    #[Test]
    public function setThrowsBecauseResourceIsReadOnly(): void
    {
        $resource = new FieldUserResource([
            'id'     => 1,
            'name'   => 'Anton',
            'email'  => 'anton@example.test',
            'secret' => 'hidden',
        ]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Resource property "name" is read-only.');

        $resource->name = 'Changed';
    }
}
