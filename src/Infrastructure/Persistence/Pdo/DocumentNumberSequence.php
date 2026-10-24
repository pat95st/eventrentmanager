<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Pdo;

use App\Domain\Numbering\DocumentNumberFormatter;
use App\Domain\Numbering\DocumentNumberingSettingsRepositoryInterface;
use App\Domain\Numbering\NumberedDocumentType;
use PDO;

/**
 * Vergibt die fortlaufende, lückenlose Dokumentnummer für EINEN der vier
 * NumberedDocumentType-Werte, admin-konfigurierbar über
 * DocumentNumberingSettings (Präfix/Trennzeichen/Ziffernanzahl/Schema).
 * Ersetzt die bisherigen, type-spezifischen InvoiceNumberSequence/
 * DunningNumberSequence durch eine einzige generische Klasse.
 *
 * KRITISCH: die nächste Nummer wird NIE aus MAX(...) bestehender Dokumente
 * ermittelt — das wäre anfällig für Race Conditions bei gleichzeitiger
 * Vergabe, und eine gelöschte Entwurfs-"Lücke" würde fälschlich erneut
 * vergeben. Stattdessen hält document_number_sequences (PK: document_type,
 * sequence_year) den Zähler explizit vor und wird per
 * SELECT ... FOR UPDATE innerhalb einer Transaktion gelesen und atomar
 * hochgezählt: die FOR-UPDATE-Zeilensperre serialisiert konkurrierende
 * Aufrufe für denselben Dokumenttyp/Jahr.
 *
 * sequence_year ist beim Simple-Schema immer 0 (ein nie zurückgesetzter
 * Zähler); beim Yearly-Schema das aktuelle Kalenderjahr — existiert für ein
 * neues Jahr noch keine Zeile, wird sie hier frisch mit next_number = 1
 * angelegt, was den Jahreswechsel-Reset ganz ohne expliziten Reset-Code
 * ergibt.
 *
 * WICHTIG: der Aufrufer muss allocateNext() innerhalb DERSELBEN Transaktion
 * aufrufen, in der auch das Dokument selbst gespeichert wird (siehe z. B.
 * MarkInvoiceSentHandler, CreateDunningNoticeHandler, CreateOfferHandler),
 * damit ein Rollback nach der Nummernvergabe auch den Zähler zurücksetzt.
 */
final class DocumentNumberSequence
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly DocumentNumberingSettingsRepositoryInterface $numberingSettings,
    ) {
    }

    /**
     * Muss innerhalb einer bereits laufenden Transaktion aufgerufen werden
     * (siehe Klassen-Doc). Liefert die fertig formatierte Dokumentnummer,
     * z. B. "RE000123" oder "RE-2026-001".
     */
    public function allocateNext(NumberedDocumentType $type): string
    {
        $settings = $this->numberingSettings->findByType($type);
        $year = DocumentNumberFormatter::previewYear($settings);

        $select = $this->pdo->prepare(
            'SELECT next_number FROM document_number_sequences WHERE document_type = :document_type AND sequence_year = :sequence_year FOR UPDATE'
        );
        $select->execute(['document_type' => $type->value, 'sequence_year' => $year]);
        $row = $select->fetch();

        if ($row === false) {
            $nextNumber = 1;
            $insert = $this->pdo->prepare(
                'INSERT INTO document_number_sequences (document_type, sequence_year, next_number) VALUES (:document_type, :sequence_year, :next_number)'
            );
            $insert->execute(['document_type' => $type->value, 'sequence_year' => $year, 'next_number' => $nextNumber + 1]);
        } else {
            $nextNumber = (int) $row['next_number'];
            $update = $this->pdo->prepare(
                'UPDATE document_number_sequences SET next_number = :next WHERE document_type = :document_type AND sequence_year = :sequence_year'
            );
            $update->execute(['next' => $nextNumber + 1, 'document_type' => $type->value, 'sequence_year' => $year]);
        }

        return DocumentNumberFormatter::format($settings, $year, $nextNumber);
    }
}
