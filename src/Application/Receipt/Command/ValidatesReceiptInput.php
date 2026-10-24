<?php

declare(strict_types=1);

namespace App\Application\Receipt\Command;

use App\Domain\Receipt\ReceiptCategory;
use App\Domain\Supplier\SupplierRepositoryInterface;
use DateTimeImmutable;

/**
 * Gemeinsame Validierung für Create-/UpdateReceiptHandler (analog
 * ValidatesInvoiceInput) — hier ist der Lieferant anders als bei Invoice
 * bewusst ZWINGEND erforderlich (siehe Aufgabenstellung), nicht optional.
 */
trait ValidatesReceiptInput
{
    /**
     * @return array{0: array<string, string>, 1: array{
     *     supplierId: string, receiptNumber: ?string, amount: string,
     *     receiptDate: ?DateTimeImmutable, category: ReceiptCategory, note: ?string
     * }}
     */
    private function validateReceiptInput(
        SupplierRepositoryInterface $suppliers,
        string $supplierId,
        string $rawReceiptNumber,
        string $rawAmount,
        string $rawReceiptDate,
        string $rawCategory,
        string $rawNote,
    ): array {
        $errors = [];

        $supplierId = trim($supplierId);
        if ($supplierId === '' || $suppliers->findById($supplierId) === null) {
            $errors['supplierId'] = 'Bitte einen gültigen Lieferanten auswählen.';
        }

        $amountValue = filter_var(str_replace(',', '.', trim($rawAmount)), FILTER_VALIDATE_FLOAT);
        if ($amountValue === false || $amountValue <= 0) {
            $errors['amount'] = 'Bitte eine gültige Summe größer als 0 angeben.';
        }

        $receiptDate = DateTimeImmutable::createFromFormat('Y-m-d', trim($rawReceiptDate));
        $dateErrors = $receiptDate !== false ? DateTimeImmutable::getLastErrors() : false;
        if ($receiptDate === false || ($dateErrors !== false && ($dateErrors['warning_count'] > 0 || $dateErrors['error_count'] > 0))) {
            $receiptDate = null;
            $errors['receiptDate'] = 'Bitte ein gültiges Datum angeben.';
        }

        $rawCategory = trim($rawCategory);
        if ($rawCategory === '') {
            $category = ReceiptCategory::Sonstiges;
        } else {
            $category = ReceiptCategory::tryFrom($rawCategory);
            if ($category === null) {
                $errors['category'] = 'Bitte eine gültige Kategorie auswählen.';
                $category = ReceiptCategory::Sonstiges;
            }
        }

        $receiptNumber = trim($rawReceiptNumber);
        $note = trim($rawNote);

        return [$errors, [
            'supplierId' => $supplierId,
            'receiptNumber' => $receiptNumber !== '' ? $receiptNumber : null,
            'amount' => $amountValue !== false ? number_format($amountValue, 2, '.', '') : '0.00',
            'receiptDate' => $receiptDate,
            'category' => $category,
            'note' => $note !== '' ? $note : null,
        ]];
    }
}
