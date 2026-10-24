<?php

declare(strict_types=1);

namespace App\Domain\ReceiptInbox;

use DateTimeImmutable;

/**
 * Eine per Import eingesammelte, auf der Allow-List stehende E-Mail mit
 * mindestens einem gültigen Anhang (PDF/JPG/PNG) — siehe
 * App\Application\ReceiptInbox\Command\ImportReceiptEmailsHandler. Das
 * Postfach selbst bleibt beim Import unangetastet (kein "gelesen"/
 * verschoben/gelöscht); diese Tabelle ist reines, eigenständiges
 * DB-Tracking, dedupliziert über messageId.
 */
final class ReceiptInboxMessage
{
    public function __construct(
        public readonly string $id,
        public readonly string $messageId,
        public readonly string $fromAddress,
        public readonly ?string $subject,
        public readonly ?DateTimeImmutable $receivedAt,
        public readonly DateTimeImmutable $importedAt,
    ) {
    }
}
