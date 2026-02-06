<?php

declare(strict_types=1);

namespace PhpSoftBox\Resource;

interface ResourceTransformerExclusionInterface extends ResourceDecoratorInterface
{
    /**
     * @return list<array{
     *     resourceType: class-string<ResourceInterface>,
     *     transformerTypes: list<class-string<ResourcePayloadTransformerInterface>>
     * }>
     */
    public function transformerExclusionRules(): array;
}
