<?php

declare(strict_types=1);

namespace App\Application\ReceiptInbox\Command;

use App\Application\Command\CommandInterface;

/** Löst einen Import-Durchlauf des Beleg-Posteingangs aus — siehe ImportReceiptEmailsHandler und bin/import-receipt-emails.php. */
final class ImportReceiptEmails implements CommandInterface
{
}
