<?php

declare(strict_types=1);

namespace PhpSoftBox\Resource;

enum RelationState
{
    /** Provider не отвечает за этот тип объекта. */
    case Unknown;

    case Unloaded;

    case Loaded;
}
