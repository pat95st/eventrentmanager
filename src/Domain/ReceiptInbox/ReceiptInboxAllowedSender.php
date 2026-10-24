<?php

declare(strict_types=1);

namespace App\Domain\ReceiptInbox;

use DateTimeImmutable;

/**
 * Eine einzelne, admin-gepflegte Absenderadresse, deren E-Mails beim
 * Posteingang-Import überhaupt berücksichtigt werden (siehe
 * App\Application\ReceiptInbox\Command\ImportReceiptEmailsHandler).
 * Bewusst ohne Bearbeiten-Funktion (nur anlegen/löschen), analog zu
 * einer einfachen Stammdaten-Liste wie Hersteller/Kategorien.
 */
final class ReceiptInboxAllowedSender
{
    public function __construct(
        public readonly string $id,
        public string $email,
        public ?string $note,
        public readonly DateTimeImmutable $createdAt,
    ) {
    }
}
