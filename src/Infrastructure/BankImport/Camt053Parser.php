<?php

declare(strict_types=1);

namespace App\Infrastructure\BankImport;

use App\Domain\BankImport\BankStatementParserInterface;
use App\Domain\BankImport\BankTransaction;
use App\Domain\BankImport\UnsupportedBankStatementException;
use DateTimeImmutable;
use DOMDocument;
use DOMXPath;

/**
 * Parst CAMT.053-Kontoauszüge (ISO 20022 XML). Nutzt bewusst
 * namespace-unabhängige local-name()-XPath-Ausdrücke statt fest kodierter
 * Namespace-Präfixe, da es mehrere, inkompatible Schemaversionen
 * (camt.053.001.02–.08) mit unterschiedlichen Namespace-URIs, aber stabilen
 * Elementnamen gibt (Ntry, Amt, CdtDbtInd, Ustrd, Dbtr/Cdtr/Nm, …).
 */
final class Camt053Parser implements BankStatementParserInterface
{
    public function parse(string $content): array
    {
        $document = new DOMDocument();
        $previousErrorSetting = libxml_use_internal_errors(true);
        $loaded = $document->loadXML($content, LIBXML_NONET);
        libxml_use_internal_errors($previousErrorSetting);

        if (!$loaded) {
            throw new UnsupportedBankStatementException('Die CAMT.053-Datei konnte nicht als XML gelesen werden.');
        }

        $xpath = new DOMXPath($document);

        $entryNodes = $xpath->query('//*[local-name()="Ntry"]');
        if ($entryNodes === false || $entryNodes->length === 0) {
            throw new UnsupportedBankStatementException('Die CAMT.053-Datei enthält keine auswertbaren Buchungen.');
        }

        $transactions = [];

        foreach ($entryNodes as $entryNode) {
            $transaction = $this->parseEntry($xpath, $entryNode);
            if ($transaction !== null) {
                $transactions[] = $transaction;
            }
        }

        if ($transactions === []) {
            throw new UnsupportedBankStatementException('Die CAMT.053-Datei enthält keine auswertbaren Buchungen.');
        }

        return $transactions;
    }

    private function parseEntry(DOMXPath $xpath, \DOMNode $entryNode): ?BankTransaction
    {
        $amountNode = $xpath->query('.//*[local-name()="Amt"]', $entryNode)?->item(0);
        $creditDebitNode = $xpath->query('.//*[local-name()="CdtDbtInd"]', $entryNode)?->item(0);
        $bookingDateNode = $xpath->query('.//*[local-name()="BookgDt"]//*[local-name()="Dt" or local-name()="DtTm"]', $entryNode)?->item(0);

        if ($amountNode === null || $creditDebitNode === null || $bookingDateNode === null) {
            return null;
        }

        $amountRaw = trim($amountNode->textContent);
        $currency = $amountNode->attributes?->getNamedItem('Ccy')?->textContent ?? 'EUR';
        $isCredit = strtoupper(trim($creditDebitNode->textContent)) === 'CRDT';

        $bookingDateRaw = substr(trim($bookingDateNode->textContent), 0, 10);

        try {
            $bookingDate = new DateTimeImmutable($bookingDateRaw);
        } catch (\Throwable) {
            return null;
        }

        $amount = number_format((float) $amountRaw, 2, '.', '');

        $purposeNode = $xpath->query('.//*[local-name()="Ustrd"]', $entryNode)?->item(0);
        $purpose = $purposeNode !== null ? trim($purposeNode->textContent) : null;
        $purpose = $purpose !== '' ? $purpose : null;

        // Zahlungspflichtiger (Debtor) bei einer Gutschrift, sonst
        // Zahlungsempfänger (Creditor) — die jeweils für die Richtung der
        // Buchung relevante Partei.
        $counterpartyNode = $isCredit
            ? $xpath->query('.//*[local-name()="RltdPties"]//*[local-name()="Dbtr"]/*[local-name()="Nm"]', $entryNode)?->item(0)
            : $xpath->query('.//*[local-name()="RltdPties"]//*[local-name()="Cdtr"]/*[local-name()="Nm"]', $entryNode)?->item(0);
        $counterpartyName = $counterpartyNode !== null ? trim($counterpartyNode->textContent) : null;
        $counterpartyName = $counterpartyName !== '' ? $counterpartyName : null;

        $referenceNode = $xpath->query('.//*[local-name()="RmtInf"]//*[local-name()="EndToEndId"]', $entryNode)?->item(0);
        $reference = $referenceNode !== null ? trim($referenceNode->textContent) : null;
        $reference = $reference !== '' ? $reference : null;

        return new BankTransaction(
            bookingDate: $bookingDate,
            amount: $amount,
            currency: (string) $currency,
            isCredit: $isCredit,
            purpose: $purpose,
            counterpartyName: $counterpartyName,
            reference: $reference,
        );
    }
}
