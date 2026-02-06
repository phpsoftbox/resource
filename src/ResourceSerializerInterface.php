<?php

declare(strict_types=1);

namespace PhpSoftBox\Resource;

interface ResourceSerializerInterface
{
    public function serialize(mixed $value): mixed;
}
