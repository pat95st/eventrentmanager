<?php

declare(strict_types=1);

namespace App\Domain\Dunning;

use DateTimeImmutable;

interface DunningNoticeRepositoryInterface
{
    public function findById(string $id): ?DunningNotice;

    /** @return DunningNotice[] Absteigend nach Erstellungsdatum (neueste zuerst). */
    public function findByInvoice(string $invoiceId): array;

    /**
     * Für die Duplikatsprüfung beim automatischen Versand (siehe
     * bin/send-dunning-notices.php): true, wenn für diese Rechnung bereits
     * eine Mahnung zu genau dieser Stufe existiert (egal ob manuell oder
     * automatisch erstellt), damit dieselbe Stufe nie zweimal automatisch
     * verschickt wird.
     */
    public function existsForInvoiceAndLevel(string $invoiceId, string $dunningLevelId): bool;

    public function save(DunningNotice $notice): void;

    public function markSent(string $id, DateTimeImmutable $sentAt): void;
}
