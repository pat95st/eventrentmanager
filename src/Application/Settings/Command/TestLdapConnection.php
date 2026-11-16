<?php

declare(strict_types=1);

namespace App\Application\Settings\Command;

use App\Application\Command\CommandInterface;

/** Checks the stored LDAP settings (connection, service account, base DN). */
final class TestLdapConnection implements CommandInterface
{
}
