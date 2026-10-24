<?php

declare(strict_types=1);

namespace App\Application\Receipt\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Domain\Receipt\ReceiptRepositoryInterface;
use App\Domain\Supplier\SupplierRepositoryInterface;
use App\Infrastructure\FileStorage\LocalFileStorage;
use DateTimeImmutable;

/**
 * Bearbeitet einen Beleg. Die PDF-Datei ist hier OPTIONAL: wird eine neue
 * mitgegeben, ersetzt sie die bisherige (alte Datei wird gelöscht), ansonsten
 * bleiben die Datei-Felder unverändert. status/paidAt werden hier bewusst
 * NICHT angefasst — dafür gibt es MarkReceiptPaid/MarkReceiptOpen.
 */
final class UpdateReceiptHandler implements CommandHandlerInterface
{
    use ValidatesReceiptInput;
    use ValidatesReceiptFile;

    public function __construct(
        private readonly ReceiptRepositoryInterface $receipts,
        private readonly SupplierRepositoryInterface $suppliers,
        private readonly LocalFileStorage $fileStorage,
    ) {
    }

    public function handle(CommandInterface $command): string
    {
        assert($command instanceof UpdateReceipt);

        $receipt = $this->receipts->findById($command->id);
        if ($receipt === null) {
            throw new NotFoundException("Beleg \"{$command->id}\" wurde nicht gefunden.");
        }

        [$errors, $data] = $this->validateReceiptInput(
            $this->suppliers,
            $command->supplierId,
            $command->receiptNumber,
            $command->rawAmount,
            $command->rawReceiptDate,
            $command->category,
            $command->note,
        );

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        if ($command->uploadedFile !== null) {
            $this->assertValidReceiptFile($command->uploadedFile);

            // WICHTIG: die neue Datei kann (z. B. bei identischem Dateityp
            // PDF->PDF) denselben storedFilename wie die alte erhalten
            // ("<receiptId>.pdf") — die alte Datei muss daher VOR dem
            // Speichern der neuen gelöscht werden. In der umgekehrten
            // Reihenfolge (erst speichern, dann die "alte" Datei löschen)
            // würde bei identischem Dateinamen genau die gerade neu
            // gespeicherte Datei wieder gelöscht, und der Beleg bliebe ohne
            // physische Datei zurück, obwohl die Datenbank weiterhin einen
            // (dann nicht mehr existierenden) storedFilename referenziert.
            // Seit PDF/JPG/PNG jeweils erlaubt sind (siehe
            // ValidatesReceiptFile), kann sich dabei auch die Dateiendung
            // ändern (z. B. bisher .pdf, neu .jpg) — die alte Datei mit
            // ihrer ALTEN Endung wird deshalb explizit über
            // $oldStoredFilename gelöscht, nicht über den (dann bereits
            // neuen) $receipt->storedFilename.
            $oldStoredFilename = $receipt->storedFilename;
            $this->fileStorage->delete('receipts', $oldStoredFilename);
            $stored = $this->fileStorage->storeUploadedFile($command->uploadedFile, 'receipts', $receipt->id);

            $receipt->originalFilename = $stored['originalFilename'];
            $receipt->storedFilename = $stored['storedFilename'];
            // Siehe CreateReceiptHandler: tatsächlicher Mime-Type statt fest
            // verdrahtetem "application/pdf" (JPG/PNG jetzt ebenfalls erlaubt).
            $receipt->mimeType = $stored['mimeType'];
            $receipt->sizeBytes = $stored['sizeBytes'];
        }

        $receipt->supplierId = $data['supplierId'];
        $receipt->receiptNumber = $data['receiptNumber'];
        $receipt->amount = $data['amount'];
        $receipt->receiptDate = $data['receiptDate'];
        $receipt->category = $data['category'];
        $receipt->note = $data['note'];
        $receipt->updatedAt = new DateTimeImmutable();

        $this->receipts->save($receipt);

        return $receipt->id;
    }
}
