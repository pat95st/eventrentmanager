<?php

declare(strict_types=1);

namespace App\Application\Settings\Command;

use App\Application\Command\CommandInterface;

/** Baut testweise eine Verbindung zum konfigurierten Postfach auf und meldet sich an, OHNE Nachrichten abzurufen — siehe TestMailboxConnectionHandler. */
final class TestMailboxConnection implements CommandInterface
{
}
