<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Http\Api\Fixtures;

/** Test fixture for CommandFactoryTest. */
final class ObjectCommand
{
    public function __construct(public readonly ?\stdClass $input = null)
    {
    }
}
