<?php

declare(strict_types=1);

namespace PhpSoftBox\Resource\Tests;

use InvalidArgumentException;
use PhpSoftBox\Resource\Resource;
use PhpSoftBox\Resource\ResourceInterface;
use PhpSoftBox\Resource\ResourcePayloadTransformerInterface;
use PhpSoftBox\Resource\ResourcePayloadTransformerRegistry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ResourcePayloadTransformerRegistry::class)]
final class ResourcePayloadTransformerRegistryTest extends TestCase
{
    #[Test]
    public function resolvesExactParentAndInterfaceRegistrationsByPriorityAndOrder(): void
    {
        $calls    = [];
        $registry = new ResourcePayloadTransformerRegistry();

        $interfaceTransformer = new RecordingTransformer('interface', $calls);
        $parentTransformer    = new RecordingTransformer('parent', $calls);
        $exactTransformer     = new RecordingTransformer('exact', $calls);

        $registry->register(RegistryResourceMarker::class, $interfaceTransformer, 10);
        $registry->register(RegistryParentResource::class, $parentTransformer, 10);
        $registry->register(RegistryChildResource::class, $exactTransformer, 20);
        $registry->register(RegistryChildResource::class, $exactTransformer, 20);

        self::assertSame(
            [$exactTransformer, $interfaceTransformer, $parentTransformer],
            $registry->for(new RegistryChildResource([])),
        );
    }

    #[Test]
    public function rejectsTypeThatIsNotAResource(): void
    {
        $registry = new ResourcePayloadTransformerRegistry();

        $this->expectException(InvalidArgumentException::class);

        $registry->register(self::class, new RecordingTransformer('invalid'));
    }
}

interface RegistryResourceMarker extends ResourceInterface
{
}

class RegistryParentResource extends Resource implements RegistryResourceMarker
{
    public function toArray(): array
    {
        return (array) $this->resource;
    }
}

final class RegistryChildResource extends RegistryParentResource
{
}

final class RecordingTransformer implements ResourcePayloadTransformerInterface
{
    /**
     * @param list<string> $calls
     */
    public function __construct(
        private readonly string $name,
        private array &$calls = [],
    ) {
    }

    public function transform(array $payload, ResourceInterface $resource): array
    {
        $this->calls[] = $this->name;

        return $payload;
    }
}
