<?php

declare(strict_types=1);

namespace PhpSoftBox\Resource\Tests;

use PhpSoftBox\Resource\ApiResponse;
use PhpSoftBox\Resource\RelationState;
use PhpSoftBox\Resource\RelationStateProviderInterface;
use PhpSoftBox\Resource\RequiredResourceValueMissingException;
use PhpSoftBox\Resource\Resource;
use PhpSoftBox\Resource\ResourceCollection;
use PhpSoftBox\Resource\ResourceSerializer;
use PhpSoftBox\Resource\Tests\Fixtures\ConditionalUserResource;
use PhpSoftBox\Resource\Tests\Fixtures\PivotAwareResource;
use PhpSoftBox\Resource\Tests\Fixtures\PivotAwareValue;
use PhpSoftBox\Resource\Tests\Fixtures\RelationStateValue;
use PhpSoftBox\Resource\Tests\Fixtures\RequiredConditionalResource;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ApiResponse::class)]
final class ResourceConditionalTest extends TestCase
{
    #[Test]
    public function serializerContextOverridesPublicPropertyFallbackForRelations(): void
    {
        $value       = (object) ['name' => 'Arthur', 'role' => 'admin'];
        $loadedState = RelationState::Unloaded;
        $provider    = new class ($value, $loadedState) implements RelationStateProviderInterface {
            public function __construct(
                private readonly object $expected,
                public RelationState $state,
            ) {
            }

            public function relationState(object $resource, string $relation): RelationState
            {
                return $resource === $this->expected && $relation === 'role'
                    ? $this->state
                    : RelationState::Unknown;
            }
        };

        $serializer = new ResourceSerializer(relationStateProvider: $provider);
        $resource   = new ConditionalUserResource($value);

        self::assertSame(['name' => 'Arthur'], $serializer->serialize($resource));

        $provider->state = RelationState::Loaded;

        self::assertSame(
            ['name' => 'Arthur', 'role' => 'admin'],
            $serializer->serialize($resource),
        );
    }

    #[Test]
    public function requireLoadedUsesSerializerRelationStateProvider(): void
    {
        $value = new RelationStateValue(
            role: null,
            posts_count: 2,
            posts_exists: true,
            posts_likes_sum: 15,
        );

        $provider = new class ($value) implements RelationStateProviderInterface {
            public function __construct(
                private readonly object $expected,
            ) {
            }

            public function relationState(object $resource, string $relation): RelationState
            {
                return $resource === $this->expected && $relation === 'role'
                    ? RelationState::Loaded
                    : RelationState::Unknown;
            }
        };

        self::assertSame(
            [
                'role'          => null,
                'postsCount'    => 2,
                'postsExists'   => true,
                'postsLikesSum' => 15,
            ],
            new ResourceSerializer(relationStateProvider: $provider)
                ->serialize(new RequiredConditionalResource($value)),
        );
    }

    #[Test]
    public function passesSerializationContextToNestedResources(): void
    {
        $value    = (object) ['name' => 'Arthur', 'role' => 'admin'];
        $provider = new class ($value) implements RelationStateProviderInterface {
            public function __construct(
                private readonly object $expected,
            ) {
            }

            public function relationState(object $resource, string $relation): RelationState
            {
                return $resource === $this->expected && $relation === 'role'
                    ? RelationState::Loaded
                    : RelationState::Unknown;
            }
        };

        $root = new class (new ConditionalUserResource($value)) extends Resource {
            public function toArray(): array
            {
                return ['child' => $this->resource];
            }
        };

        self::assertSame(
            ['child' => ['name' => 'Arthur', 'role' => 'admin']],
            new ResourceSerializer(relationStateProvider: $provider)->serialize($root),
        );
    }

    /**
     * Проверяет условный вывод атрибутов через when.
     */
    #[Test]
    public function whenSkipsMissingValues(): void
    {
        $resource = new ConditionalUserResource(['name' => 'Arthur']);

        $response = ApiResponse::success($resource);

        self::assertSame(
            [
                'data' => [
                    'name' => 'Arthur',
                ],
                'meta'   => [],
                'errors' => null,
            ],
            $response->toArray(),
        );
    }

    /**
     * Проверяет whenLoaded для массивов.
     */
    #[Test]
    public function whenLoadedUsesPresentAttributes(): void
    {
        $resource = new ConditionalUserResource(['name' => 'Arthur', 'role' => 'admin']);

        $response = ApiResponse::success($resource);

        self::assertSame(
            [
                'data' => [
                    'name' => 'Arthur',
                    'role' => 'admin',
                ],
                'meta'   => [],
                'errors' => null,
            ],
            $response->toArray(),
        );
    }

    #[Test]
    public function requireHelpersReturnLoadedRelationsAndRegularAttributes(): void
    {
        $resource = new RequiredConditionalResource(new RelationStateValue(
            role: null,
            posts_count: 2,
            posts_exists: true,
            posts_likes_sum: 15,
            loadedRelations: ['role'],
        ));

        self::assertSame(
            [
                'data' => [
                    'role'          => null,
                    'postsCount'    => 2,
                    'postsExists'   => true,
                    'postsLikesSum' => 15,
                ],
                'meta'   => [],
                'errors' => null,
            ],
            ApiResponse::success($resource)->toArray(),
        );
    }

    #[Test]
    public function requireLoadedThrowsForMissingRelation(): void
    {
        $resource = new RequiredConditionalResource(new RelationStateValue(
            posts_count: 2,
            posts_exists: true,
            posts_likes_sum: 15,
        ));

        $this->expectException(RequiredResourceValueMissingException::class);
        $this->expectExceptionMessage('Required relation "role" is not loaded');

        ApiResponse::success($resource)->toArray();
    }

    #[Test]
    public function onlyDoesNotEvaluateExcludedRequireHelpers(): void
    {
        $resource = $this->resourceWithAliasedRequiredRelation();

        self::assertSame(
            ['id' => 10],
            new ResourceSerializer()->serialize($resource->only('id')),
        );
        self::assertSame(
            ['id' => 10],
            new ResourceSerializer()->serialize($resource->except('fulfillment_schemes')),
        );
        self::assertSame(['id' => 10], $resource->only('id')->toArray());
    }

    #[Test]
    public function onlyDefersAllRequireHelperVariants(): void
    {
        $resource = new RequiredConditionalResource(new RelationStateValue());

        self::assertSame(
            [],
            new ResourceSerializer()->serialize($resource->only('unrelated')),
        );
    }

    #[Test]
    public function collectionOnlyDoesNotEvaluateExcludedRequireHelpers(): void
    {
        $collection = new ResourceCollection([
            $this->resourceWithAliasedRequiredRelation(),
            $this->resourceWithAliasedRequiredRelation(),
        ])->only('id');

        self::assertSame(
            [['id' => 10], ['id' => 10]],
            new ResourceSerializer()->serialize($collection),
        );
        self::assertSame([['id' => 10], ['id' => 10]], $collection->toArray());
    }

    #[Test]
    public function selectedRequireHelperStillEnforcesContract(): void
    {
        $resource = $this->resourceWithAliasedRequiredRelation()->only('fulfillment_schemes');

        $this->expectException(RequiredResourceValueMissingException::class);
        $this->expectExceptionMessage('Required relation "fulfillmentSchemes" is not loaded');

        new ResourceSerializer()->serialize($resource);
    }

    /**
     * Проверяет whenCounted для счётчиков.
     */
    #[Test]
    public function whenCountedUsesSnakeCaseCountAttribute(): void
    {
        $resource = new ConditionalUserResource(['name' => 'Arthur', 'posts_count' => 2]);

        $response = ApiResponse::success($resource);

        self::assertSame(
            [
                'data' => [
                    'name'       => 'Arthur',
                    'postsCount' => 2,
                ],
                'meta'   => [],
                'errors' => null,
            ],
            $response->toArray(),
        );
    }

    /**
     * Проверяет conditional helpers для exists-флага и агрегатов отношений.
     */
    #[Test]
    public function aggregateHelpersUseSnakeCaseAttributes(): void
    {
        $resource = new ConditionalUserResource([
            'name'            => 'Arthur',
            'posts_exists'    => true,
            'posts_likes_sum' => 8,
            'posts_likes_max' => 5,
        ]);

        $response = ApiResponse::success($resource);

        self::assertSame(
            [
                'data' => [
                    'name'          => 'Arthur',
                    'postsExists'   => true,
                    'postsLikesSum' => 8,
                    'postsLikesMax' => 5,
                ],
                'meta'   => [],
                'errors' => null,
            ],
            $response->toArray(),
        );
    }

    private function resourceWithAliasedRequiredRelation(): Resource
    {
        return new class (['id' => 10]) extends Resource {
            public function toArray(): array
            {
                return [
                    'id'                  => $this->resource['id'],
                    'fulfillment_schemes' => $this->requireLoaded('fulfillmentSchemes'),
                ];
            }
        };
    }

    /**
     * Проверяет, что whenPivotLoaded пропускает отсутствующий pivot.
     */
    #[Test]
    public function whenPivotLoadedSkipsMissingPivot(): void
    {
        $resource = new PivotAwareResource(new PivotAwareValue('Arthur'));

        $response = ApiResponse::success($resource);

        self::assertSame(
            [
                'data' => [
                    'name' => 'Arthur',
                ],
                'meta'   => [],
                'errors' => null,
            ],
            $response->toArray(),
        );
    }

    /**
     * Проверяет вывод default и custom pivot accessor.
     */
    #[Test]
    public function whenPivotLoadedUsesDefaultAndCustomAccessors(): void
    {
        $resource = new PivotAwareResource(new PivotAwareValue(
            name: 'Arthur',
            pivot: (object) ['createdDatetime' => '2026-07-21 12:00:00'],
            membership: (object) ['expiresDatetime' => '2026-08-21 12:00:00'],
        ));

        $response = ApiResponse::success($resource);

        self::assertSame(
            [
                'data' => [
                    'name'  => 'Arthur',
                    'pivot' => [
                        'createdDatetime' => '2026-07-21 12:00:00',
                    ],
                    'membership' => [
                        'expiresDatetime' => '2026-08-21 12:00:00',
                    ],
                ],
                'meta'   => [],
                'errors' => null,
            ],
            $response->toArray(),
        );
    }
}
