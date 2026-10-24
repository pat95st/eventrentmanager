<?php

declare(strict_types=1);

namespace App\Application\Receipt\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\DocumentStorage\DocumentType;
use App\Domain\Exception\ValidationException;
use App\Domain\Receipt\Receipt;
use App\Domain\Receipt\ReceiptRepositoryInterface;
use App\Domain\Receipt\ReceiptStatus;
use App\Domain\Supplier\SupplierRepositoryInterface;
use App\Infrastructure\DocumentStorage\DocumentStoragePusher;
use App\Infrastructure\FileStorage\LocalFileStorage;
use App\Shared\Uuid;
use DateTimeImmutable;

/** Legt einen neuen Beleg an — die PDF-Datei ist hier (anders als beim Bearbeiten) zwingend erforderlich. */
final class CreateReceiptHandler implements CommandHandlerInterface
{
    use ValidatesReceiptInput;
    use ValidatesReceiptFile;

    public function __construct(
        private readonly ReceiptRepositoryInterface $receipts,
        private readonly SupplierRepositoryInterface $suppliers,
        private readonly LocalFileStorage $fileStorage,
        private readonly DocumentStoragePusher $documentStoragePusher,
    ) {
    }

    public function handle(CommandInterface $command): string
    {
        assert($command instanceof CreateReceipt);

        [$errors, $data] = $this->validateReceiptInput(
            $this->suppliers,
            $command->supplierId,
            $command->receiptNumber,
            $command->rawAmount,
            $command->rawReceiptDate,
            $command->category,
            $command->note,
        );

        if ($command->uploadedFile === null) {
            $errors['document'] = 'Bitte eine PDF-Datei hochladen.';
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $this->assertValidReceiptFile($command->uploadedFile);

        $receiptId = Uuid::v4();
        $stored = $this->fileStorage->storeUploadedFile($command->uploadedFile, 'receipts', $receiptId);

        $now = new DateTimeImmutable();
        $receipt = new Receipt(
            id: $receiptId,
            supplierId: $data['supplierId'],
            receiptNumber: $data['receiptNumber'],
            amount: $data['amount'],
            receiptDate: $data['receiptDate'],
            category: $data['category'],
            status: ReceiptStatus::Open,
            paidAt: null,
            note: $data['note'],
            originalFilename: $stored['originalFilename'],
            storedFilename: $stored['storedFilename'],
            // Der TATSÄCHLICHE, serverseitig erkannte Mime-Type (siehe
            // ValidatesReceiptFile::assertValidReceiptFile()) statt eines
            // fest verdrahteten "application/pdf" — ein Beleg darf seit der
            // Erweiterung um den E-Mail-Posteingang auch als JPG/PNG
            // vorliegen (siehe ValidatesReceiptFile).
            mimeType: $stored['mimeType'],
            sizeBytes: $stored['sizeBytes'],
            createdAt: $now,
            updatedAt: $now,
        );

        $this->receipts->save($receipt);

        // Synchroner Push auf konfigurierte Ziellaufwerke (siehe
        // DocumentStoragePusher). Die Datei-Bytes liegen hier NICHT im
        // Speicher (nur als Pfad), daher erst jetzt eingelesen — Belege
        // sind i. d. R. klein genug, das ist unproblematisch.
        $absolutePath = $this->fileStorage->absolutePath('receipts', $receipt->storedFilename);
        $contents = @file_get_contents($absolutePath);
        if ($contents !== false) {
            $this->documentStoragePusher->push(
                DocumentType::Receipt,
                $receipt->id,
                $contents,
                $receipt->originalFilename,
                ['{number}' => $receipt->receiptNumber ?? $receipt->id],
            );
        } else {
            error_log("CreateReceiptHandler: Beleg-Datei für den Ziellaufwerk-Push konnte nicht gelesen werden: {$absolutePath}");
        }

        return $receipt->id;
    }
}
