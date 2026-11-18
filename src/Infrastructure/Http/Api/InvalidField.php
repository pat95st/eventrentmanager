<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Api;

use RuntimeException;

/** @internal Raised by CommandFactory while coercing one field. */
final class InvalidField extends RuntimeException
{
}
