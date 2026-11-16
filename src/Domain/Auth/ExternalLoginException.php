<?php

declare(strict_types=1);

namespace App\Domain\Auth;

use RuntimeException;

/**
 * The external system could not be asked at all or answered unusably
 * (server unreachable, bad service account, missing e-mail attribute, ...).
 * Wrong credentials are NOT an exception, see LdapDirectoryInterface.
 * The message is meant for administrators (settings page, error log).
 */
final class ExternalLoginException extends RuntimeException
{
}
