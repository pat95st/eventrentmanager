<?php

declare(strict_types=1);

namespace App\Application\ReceiptInbox\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Application\Receipt\Command\ValidatesReceiptInput;
use App\Domain\DocumentStorage\DocumentType;
use App\Domain\Exception\ConflictException;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Domain\Receipt\Receipt;
use App\Domain\Receipt\ReceiptRepositoryInterface;
use App\Domain\Receipt\ReceiptStatus;
use App\Domain\ReceiptInbox\ReceiptInboxAttachmentRepositoryInterface;
use App\Domain\Supplier\SupplierRepositoryInterface;
use App\Infrastructure\DocumentStorage\DocumentStoragePusher;
use App\Infrastructure\FileStorage\LocalFileStorage;
use App\Shared\Uuid;
use DateTimeImmutable;

/**
 * Wandelt einen Beleg-Posteingang-Anhang in einen "echten" Beleg um (siehe
 * resources/views/receipts/inbox.php, "Zu Beleg umwandeln"). Nutzt dieselbe
 * Validierung wie CreateReceipt (ValidatesReceiptInput), aber ohne
 * Datei-Upload — die Datei liegt bereits unter storage/uploads/receipt-inbox/.
 *
 * Die Anhang-Datei wird bewusst nach storage/uploads/receipts/ KOPIERT
 * (nicht verschoben) und die Inbox-Kopie NICHT gelöscht: schlägt das
 * Formular später nochmal fehl oder wird ein falscher Lieferant korrigiert,
 * soll der Anhang im Posteingang nicht verloren gehen. Erst nach
 * erfolgreicher Beleg-Erstellung wird converted_receipt_id auf dem Anhang
 * gesetzt.
 */
final class ConvertReceiptInboxAttachmentToReceiptHandler implements CommandHandlerInterface
{
    use ValidatesReceiptInput;

    public function __construct(
        private readonly ReceiptInboxAttachmentRepositoryInterface $inboxAttachments,
        private readonly ReceiptRepositoryInterface $receipts,
        private readonly SupplierRepositoryInterface $suppliers,
        private readonly LocalFileStorage $fileStorage,
        private readonly DocumentStoragePusher $documentStoragePusher,
    ) {
    }

    public function handle(CommandInterface $command): string
    {
        assert($command instanceof ConvertReceiptInboxAttachmentToReceipt);

        $attachment = $this->inboxAttachments->findById($command->attachmentId);
        if ($attachment === null) {
            throw new NotFoundException("Posteingang-Anhang \"{$command->attachmentId}\" wurde nicht gefunden.");
        }

        if ($attachment->isConverted()) {
            throw new ConflictException('Dieser Anhang wurde bereits zu einem Beleg umgewandelt.');
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

        $receiptId = Uuid::v4();
        $sourcePath = $this->fileStorage->absolutePath('receipt-inbox', $attachment->storedFilename);
        $stored = $this->fileStorage->storeLocalFile($sourcePath, 'receipts', $receiptId, $attachment->originalFilename, deleteSource: false);

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
            mimeType: $attachment->mimeType,
            sizeBytes: $stored['sizeBytes'],
            createdAt: $now,
            updatedAt: $now,
        );

        $this->receipts->save($receipt);

        $attachment->convertedReceiptId = $receipt->id;
        $this->inboxAttachments->save($attachment);

        // Synchroner Push auf konfigurierte Ziellaufwerke (siehe
        // DocumentStoragePusher), analog zu CreateReceiptHandler.
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
            error_log("ConvertReceiptInboxAttachmentToReceiptHandler: Beleg-Datei für den Ziellaufwerk-Push konnte nicht gelesen werden: {$absolutePath}");
        }

        return $receipt->id;
    }
}
