<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\Command\CommandBus;
use App\Application\Query\QueryBus;
use App\Application\ReceiptInbox\Command\ConvertReceiptInboxAttachmentToReceipt;
use App\Application\ReceiptInbox\Query\ListReceiptInboxMessages;
use App\Application\Supplier\Query\ListSuppliers;
use App\Domain\Exception\ConflictException;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Domain\Receipt\ReceiptCategory;
use App\Domain\ReceiptInbox\ReceiptInboxAttachment;
use App\Domain\ReceiptInbox\ReceiptInboxAttachmentRepositoryInterface;
use App\Infrastructure\FileStorage\LocalFileStorage;
use App\Infrastructure\Http\Auth\CurrentUser;
use App\Infrastructure\Http\Flash;
use App\Infrastructure\Http\Pagination;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;
use App\Infrastructure\Http\View;
use function App\Infrastructure\Http\t;

final class ReceiptInboxController
{
    public function __construct(
        private readonly CommandBus $commands,
        private readonly QueryBus $queries,
        private readonly ReceiptInboxAttachmentRepositoryInterface $attachments,
        private readonly LocalFileStorage $fileStorage,
    ) {
    }

    public function index(Request $request): void
    {
        $page = Pagination::page($request);
        $pageSize = Pagination::pageSize($request, CurrentUser::get());

        $result = $this->queries->ask(new ListReceiptInboxMessages($page, $pageSize));

        echo View::page('receipts/inbox', [
            'entries' => $result->items,
            'page' => $result->page,
            'pageSize' => $result->pageSize,
            'totalCount' => $result->totalCount,
            'totalPages' => $result->totalPages(),
            'paginationBaseUrl' => '/belege/posteingang',
            'paginationQuery' => [],
        ], activeNav: 'receipt_inbox', title: t('receipts.inbox.title'));
    }

    public function downloadAttachment(Request $request): void
    {
        $id = $request->param('id');
        $attachment = $this->attachments->findById($id);

        if ($attachment === null) {
            Response::notFound(t('receipts.inbox.attachment_not_found'));

            return;
        }

        $absolutePath = $this->fileStorage->absolutePath('receipt-inbox', $attachment->storedFilename);
        Response::file($absolutePath, $attachment->originalFilename, $attachment->mimeType);
    }

    public function createReceipt(Request $request): void
    {
        $id = $request->param('id');
        $attachment = $this->attachments->findById($id);

        if ($attachment === null) {
            Response::notFound(t('receipts.inbox.attachment_not_found'));

            return;
        }

        if ($attachment->isConverted()) {
            Flash::error(t('receipts.inbox.already_converted'));
            Response::redirect('/belege/posteingang');

            return;
        }

        $this->renderConvertForm($attachment->id, $attachment, [], []);
    }

    public function storeReceipt(Request $request): void
    {
        $id = $request->param('id');
        $attachment = $this->attachments->findById($id);

        if ($attachment === null) {
            Response::notFound(t('receipts.inbox.attachment_not_found'));

            return;
        }

        $command = new ConvertReceiptInboxAttachmentToReceipt(
            attachmentId: $id,
            supplierId: $request->input('supplier_id', '') ?? '',
            receiptNumber: $request->input('receipt_number', '') ?? '',
            rawAmount: $request->input('amount', '') ?? '',
            rawReceiptDate: $request->input('receipt_date', '') ?? '',
            category: $request->input('category', '') ?? '',
            note: $request->input('note', '') ?? '',
        );

        try {
            $receiptId = $this->commands->dispatch($command);
            Flash::success(t('receipts.inbox.flash.converted'));
            Response::redirect('/belege/' . $receiptId);
        } catch (ValidationException $e) {
            $this->renderConvertForm($id, $attachment, $e->errors(), $this->oldFromCommand($command));
        } catch (ConflictException $e) {
            Flash::error($e->getMessage());
            Response::redirect('/belege/posteingang');
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());
        }
    }

    /** @param array<string, string> $errors @param array<string, mixed> $old */
    private function renderConvertForm(string $attachmentId, ReceiptInboxAttachment $attachment, array $errors, array $old): void
    {
        $suppliers = $this->queries->ask(new ListSuppliers(page: null, pageSize: null))->items;

        echo View::page('receipts/inbox_convert', [
            'attachment' => $attachment,
            'suppliers' => $suppliers,
            'categories' => ReceiptCategory::cases(),
            'errors' => $errors,
            'old' => $old,
        ], activeNav: 'receipt_inbox', title: t('receipts.inbox.convert_title'));
    }

    /** @return array<string, mixed> */
    private function oldFromCommand(ConvertReceiptInboxAttachmentToReceipt $command): array
    {
        return [
            'supplier_id' => $command->supplierId,
            'receipt_number' => $command->receiptNumber,
            'amount' => $command->rawAmount,
            'receipt_date' => $command->rawReceiptDate,
            'category' => $command->category,
            'note' => $command->note,
        ];
    }
}
