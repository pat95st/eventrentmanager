<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\Command\CommandBus;
use App\Application\Query\QueryBus;
use App\Application\Receipt\Command\CreateReceipt;
use App\Application\Receipt\Command\DeleteReceipt;
use App\Application\Receipt\Command\MarkReceiptOpen;
use App\Application\Receipt\Command\MarkReceiptPaid;
use App\Application\Receipt\Command\UpdateReceipt;
use App\Application\Receipt\Query\GetReceipt;
use App\Application\Receipt\Query\ListReceipts;
use App\Application\Supplier\Query\ListSuppliers;
use App\Domain\Exception\ConflictException;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Domain\Receipt\Receipt;
use App\Domain\Receipt\ReceiptCategory;
use App\Domain\Receipt\ReceiptStatus;
use App\Infrastructure\FileStorage\LocalFileStorage;
use App\Infrastructure\Http\Auth\CurrentUser;
use App\Infrastructure\Http\Flash;
use App\Infrastructure\Http\Pagination;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;
use App\Infrastructure\Http\View;
use function App\Infrastructure\Http\t;

final class ReceiptController
{
    public function __construct(
        private readonly CommandBus $commands,
        private readonly QueryBus $queries,
        private readonly LocalFileStorage $fileStorage,
    ) {
    }

    public function index(Request $request): void
    {
        $search = $request->query('q');
        $supplierId = $this->nullIfEmpty($request->query('supplier_id'));
        $statusRaw = $this->nullIfEmpty($request->query('status'));
        $status = $statusRaw !== null ? ReceiptStatus::tryFrom($statusRaw) : null;
        $categoryRaw = $this->nullIfEmpty($request->query('category'));
        $category = $categoryRaw !== null ? ReceiptCategory::tryFrom($categoryRaw) : null;
        $page = Pagination::page($request);
        $pageSize = Pagination::pageSize($request, CurrentUser::get());

        $result = $this->queries->ask(new ListReceipts($search, $supplierId, $status, $category, $page, $pageSize));
        $suppliers = $this->queries->ask(new ListSuppliers(page: null, pageSize: null))->items;

        $viewData = [
            'receipts' => $result->items,
            'suppliers' => $suppliers,
            'search' => $search ?? '',
            'selectedSupplierId' => $supplierId ?? '',
            'selectedStatus' => $status,
            'statuses' => ReceiptStatus::cases(),
            'selectedCategory' => $category,
            'categories' => ReceiptCategory::cases(),
            'page' => $result->page,
            'pageSize' => $result->pageSize,
            'totalCount' => $result->totalCount,
            'totalPages' => $result->totalPages(),
            'paginationBaseUrl' => '/belege',
            'paginationQuery' => array_filter([
                'q' => $search ?? '',
                'supplier_id' => $supplierId ?? '',
                'status' => $statusRaw ?? '',
                'category' => $categoryRaw ?? '',
            ], static fn ($value) => $value !== ''),
        ];

        if (Pagination::isAjax($request)) {
            echo View::render('receipts/_table', $viewData);

            return;
        }

        echo View::page('receipts/index', $viewData, activeNav: 'receipts', title: t('receipts.title'));
    }

    public function create(Request $request): void
    {
        $this->renderForm(null, [], []);
    }

    public function store(Request $request): void
    {
        $command = new CreateReceipt(
            supplierId: $request->input('supplier_id', '') ?? '',
            receiptNumber: $request->input('receipt_number', '') ?? '',
            rawAmount: $request->input('amount', '') ?? '',
            rawReceiptDate: $request->input('receipt_date', '') ?? '',
            category: $request->input('category', '') ?? '',
            note: $request->input('note', '') ?? '',
            uploadedFile: $request->file('document'),
        );

        try {
            $receiptId = $this->commands->dispatch($command);
            Flash::success(t('receipts.flash.created'));
            Response::redirect('/belege/' . $receiptId);
        } catch (ValidationException $e) {
            $this->renderForm(null, $e->errors(), $this->oldFromCommand($command));
        }
    }

    public function show(Request $request): void
    {
        $id = $request->param('id');

        try {
            $detail = $this->queries->ask(new GetReceipt($id));
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());

            return;
        }

        echo View::page('receipts/show', [
            'detail' => $detail,
        ], activeNav: 'receipts', title: t('receipts.title'));
    }

    public function edit(Request $request): void
    {
        $id = $request->param('id');

        try {
            $detail = $this->queries->ask(new GetReceipt($id));
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());

            return;
        }

        $this->renderForm($detail->receipt, [], [], $detail->receipt->id);
    }

    public function update(Request $request): void
    {
        $id = $request->param('id');

        $command = new UpdateReceipt(
            id: $id,
            supplierId: $request->input('supplier_id', '') ?? '',
            receiptNumber: $request->input('receipt_number', '') ?? '',
            rawAmount: $request->input('amount', '') ?? '',
            rawReceiptDate: $request->input('receipt_date', '') ?? '',
            category: $request->input('category', '') ?? '',
            note: $request->input('note', '') ?? '',
            uploadedFile: $request->file('document'),
        );

        try {
            $this->commands->dispatch($command);
            Flash::success(t('receipts.flash.updated'));
            Response::redirect('/belege/' . $id);
        } catch (ValidationException $e) {
            $detail = $this->queries->ask(new GetReceipt($id));
            $this->renderForm($detail->receipt, $e->errors(), $this->oldFromCommand($command), $id);
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());
        }
    }

    public function destroy(Request $request): void
    {
        $id = $request->param('id');

        try {
            $this->commands->dispatch(new DeleteReceipt($id));
            Flash::success(t('receipts.flash.deleted'));
            Response::redirect('/belege');
        } catch (ConflictException $e) {
            Flash::error($e->getMessage());
            Response::redirect('/belege/' . $id);
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());
        }
    }

    public function markPaid(Request $request): void
    {
        $id = $request->param('id');

        try {
            $this->commands->dispatch(new MarkReceiptPaid($id));
            Flash::success(t('receipts.flash.marked_paid'));
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());

            return;
        }

        Response::redirect('/belege/' . $id);
    }

    public function markOpen(Request $request): void
    {
        $id = $request->param('id');

        try {
            $this->commands->dispatch(new MarkReceiptOpen($id));
            Flash::success(t('receipts.flash.marked_open'));
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());

            return;
        }

        Response::redirect('/belege/' . $id);
    }

    public function download(Request $request): void
    {
        $id = $request->param('id');

        try {
            $detail = $this->queries->ask(new GetReceipt($id));
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());

            return;
        }

        $receipt = $detail->receipt;
        $absolutePath = $this->fileStorage->absolutePath('receipts', $receipt->storedFilename);
        Response::file($absolutePath, $receipt->originalFilename, $receipt->mimeType);
    }

    /** @param array<string, string> $errors @param array<string, mixed> $old */
    private function renderForm(?Receipt $receipt, array $errors, array $old, ?string $editingId = null): void
    {
        $suppliers = $this->queries->ask(new ListSuppliers(page: null, pageSize: null))->items;

        echo View::page('receipts/form', [
            'receipt' => $receipt,
            'suppliers' => $suppliers,
            'categories' => ReceiptCategory::cases(),
            'errors' => $errors,
            'old' => $old,
            'editingId' => $editingId,
        ], activeNav: 'receipts', title: $receipt !== null ? t('receipts.edit') : t('receipts.new'));
    }

    /** @return array<string, mixed> */
    private function oldFromCommand(CreateReceipt|UpdateReceipt $command): array
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

    private function nullIfEmpty(?string $value): ?string
    {
        return $value === null || $value === '' ? null : $value;
    }
}
