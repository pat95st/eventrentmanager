<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Pdo;

use App\Domain\Settings\InvoiceSettingsRepositoryInterface;
use PDO;

/**
 * Vergibt die fortlaufende, lückenlose, gesetzlich relevante
 * Rechnungsnummer (siehe MarkInvoiceSentHandler) — bewusst als eigener,
 * kleiner Baustein statt Teil von MariadbInvoiceRepository, damit die
 * Transaktions-/Sperrlogik isoliert und auditierbar bleibt.
 *
 * KRITISCH: die nächste Nummer wird NIE aus MAX(invoice_number) der
 * bestehenden Rechnungen ermittelt — das wäre sowohl anfällig für Race
 * Conditions bei gleichzeitigem "Als versendet markieren" zweier Rechnungen,
 * als auch dafür, dass eine gelöschte Entwurfs-"Lücke" fälschlich erneut
 * vergeben würde. Stattdessen hält die Singleton-Zeile invoice_sequences
 * (id = 1) den Zähler explizit vor und wird per SELECT ... FOR UPDATE
 * innerhalb einer Transaktion gelesen und atomar hochgezählt: die
 * FOR-UPDATE-Zeilensperre serialisiert konkurrierende Aufrufe, sodass zwei
 * gleichzeitige "Versenden"-Aktionen niemals dieselbe Nummer erhalten
 * können, selbst wenn Rechnungen zwischendurch gelöscht werden.
 *
 * WICHTIG: der Aufrufer (MarkInvoiceSentHandler) muss allocateNext()
 * innerhalb DERSELBEN Transaktion aufrufen, in der auch die Rechnung selbst
 * gespeichert wird, damit bei einem Fehlschlag nach der Nummernvergabe kein
 * "verbrauchter" Nummernblock ungenutzt bleibt (bzw. damit ein Rollback auch
 * den Zähler zurücksetzt).
 */
final class InvoiceNumberSequence
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly InvoiceSettingsRepositoryInterface $invoiceSettings,
    ) {
    }

    /**
     * Muss innerhalb einer bereits laufenden Transaktion aufgerufen werden
     * (siehe Klassen-Doc). Liefert die fertig formatierte Rechnungsnummer,
     * z. B. "RE-000123".
     */
    public function allocateNext(): string
    {
        // FOR UPDATE sperrt genau diese eine Zeile bis zum Commit/Rollback
        // der umgebenden Transaktion — jeder weitere gleichzeitige Aufruf
        // blockiert hier, statt dieselbe Nummer zu lesen.
        $select = $this->pdo->prepare('SELECT next_number FROM invoice_sequences WHERE id = 1 FOR UPDATE');
        $select->execute();
        $nextNumber = (int) $select->fetchColumn();

        $update = $this->pdo->prepare('UPDATE invoice_sequences SET next_number = :next WHERE id = 1');
        $update->execute(['next' => $nextNumber + 1]);

        $prefix = $this->invoiceSettings->get()->invoiceNumberPrefix;

        return $prefix . str_pad((string) $nextNumber, 6, '0', STR_PAD_LEFT);
    }
}
