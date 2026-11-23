<?php

declare(strict_types=1);

namespace App\Domain\Exception;

use RuntimeException;

/** The acting user is not allowed to do this (e.g. edit another driver's trip). */
final class ForbiddenException extends RuntimeException
{
}
