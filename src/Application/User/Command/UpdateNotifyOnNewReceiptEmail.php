<?php

declare(strict_types=1);

namespace App\Application\User\Command;

use App\Application\Command\CommandInterface;

/**
 * Ändert, ob der angemeldete Benutzer per E-Mail benachrichtigt werden
 * möchte, wenn der Beleg-Posteingang-Cronjob neue Nachrichten eingesammelt
 * hat (nur wirksam, wenn der Benutzer außerdem Administrator ist, siehe
 * App\Application\ReceiptInbox\Command\ImportReceiptEmailsHandler). Der
 * Controller übergibt hier ausschließlich die eigene userId (aus
 * CurrentUser::get()), analog zu UpdateLocale/UpdateDefaultPageSize.
 */
final class UpdateNotifyOnNewReceiptEmail implements CommandInterface
{
    public function __construct(
        public readonly string $userId,
        public readonly bool $notify,
    ) {
    }
}
