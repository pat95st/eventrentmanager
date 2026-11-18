<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers\Api;

use App\Application\Banking\Query\AccountPeriod;
use App\Application\Banking\Query\GetAccountLedger;
use App\Application\Command\CommandBus;
use App\Application\Purchase\Command\CreatePurchaseOrder;
use App\Application\Purchase\Command\DeletePurchaseOrder;
use App\Application\Purchase\Command\MarkPurchaseOrderCancelled;
use App\Application\Purchase\Command\MarkPurchaseOrderOrdered;
use App\Application\Purchase\Command\ReceivePurchaseOrderItem;
use App\Application\Purchase\Command\UpdatePurchaseOrder;
use App\Application\Purchase\Query\GetPurchaseOrder;
use App\Application\Purchase\Query\ListPurchaseOrders;
use App\Application\Purchase\Query\PurchaseOrderDetail;
use App\Application\Query\QueryBus;
use App\Application\Receipt\Command\CreateReceipt;
use App\Application\Receipt\Command\DeleteReceipt;
use App\Application\Receipt\Command\MarkReceiptOpen;
use App\Application\Receipt\Command\MarkReceiptPaid;
use App\Application\Receipt\Command\UpdateReceipt;
use App\Application\Receipt\Query\GetReceipt;
use App\Application\Receipt\Query\ListReceipts;
use App\Application\Receipt\Query\ReceiptDetail;
use App\Domain\Settings\BankAccountRepositoryInterface;
use App\Infrastructure\FileStorage\LocalFileStorage;
use App\Infrastructure\Http\Api\ApiController;
use App\Infrastructure\Http\Api\ApiException;
use App\Infrastructure\Http\Api\ApiRequest;
use App\Infrastructure\Http\Api\ApiResponse;
use App\Infrastructure\Http\Api\CommandFactory;
use App\Infrastructure\Http\Api\Normalizer;

/**
 * /api/v1/receipts (Belege), /api/v1/bank-accounts (Konten, read only),
 * /api/v1/purchase-orders (Bestellungen) — scopes finance:read / finance:write.
 */
final class FinanceApiController extends ApiController
{
    /** Body field => command parameter for receipts (the commands take raw form values). */
    private const RECEIPT_ALIASES = ['amount' => 'rawAmount', 'receipt_date' => 'rawReceiptDate'];

    public function __construct(
        QueryBus $queries,
        CommandBus $commands,
        private readonly LocalFileStorage $fileStorage,
        private readonly BankAccountRepositoryInterface $bankAccounts,
    ) {
        parent::__construct($queries, $commands);
    }

    // --- Receipts ----------------------------------------------------------

    public function receipts(ApiRequest $request): ApiResponse
    {
        $result = $this->ask(new ListReceipts(supplierId: $request->query('supplier_id'), page: 1, pageSize: 1_000_000));
        $items = [];
        foreach ($result->items as $entry) {
            $items[] = $this->receiptToArray($entry->receipt, $entry->supplier ?? null);
        }

        return $this->listResponse($request, $items);
    }

    public function receipt(ApiRequest $request): ApiResponse
    {
        return ApiResponse::data($this->receiptDetail($request->param('id')));
    }

    /** multipart/form-data with the fields of the receipt and the document in "file". */
    public function storeReceipt(ApiRequest $request): ApiResponse
    {
        $id = $this->run(CreateReceipt::class, $request, ['uploadedFile' => $request->file('file')], null, self::RECEIPT_ALIASES);

        return $this->created($this->receiptDetail((string) $id));
    }

    /**
     * PATCH (JSON) or POST /receipts/{id} (multipart, to replace the file).
     */
    public function updateReceipt(ApiRequest $request): ApiResponse
    {
        $id = $request->param('id');
        $receipt = $this->loadReceipt($id)->receipt;
        $current = CommandFactory::props($receipt) + [
            'rawAmount' => $receipt->amount,
            'rawReceiptDate' => $receipt->receiptDate->format('Y-m-d'),
            'receiptNumber' => $receipt->receiptNumber ?? '',
            'note' => $receipt->note ?? '',
        ];
        $this->run(UpdateReceipt::class, $request, ['id' => $id, 'uploadedFile' => $request->file('file')], $current, self::RECEIPT_ALIASES);

        return ApiResponse::data($this->receiptDetail($id));
    }

    public function destroyReceipt(ApiRequest $request): ApiResponse
    {
        $this->loadReceipt($request->param('id'));
        $this->dispatch(new DeleteReceipt($request->param('id')));

        return ApiResponse::noContent();
    }

    public function markReceiptPaid(ApiRequest $request): ApiResponse
    {
        $this->loadReceipt($request->param('id'));
        $this->dispatch(new MarkReceiptPaid($request->param('id')));

        return ApiResponse::data($this->receiptDetail($request->param('id')));
    }

    public function markReceiptOpen(ApiRequest $request): ApiResponse
    {
        $this->loadReceipt($request->param('id'));
        $this->dispatch(new MarkReceiptOpen($request->param('id')));

        return ApiResponse::data($this->receiptDetail($request->param('id')));
    }

    public function receiptFile(ApiRequest $request): ApiResponse
    {
        $receipt = $this->loadReceipt($request->param('id'))->receipt;

        return ApiResponse::file($this->fileStorage->absolutePath('receipts', $receipt->storedFilename), $receipt->originalFilename, $receipt->mimeType);
    }

    // --- Bank accounts -----------------------------------------------------

    public function bankAccounts(ApiRequest $request): ApiResponse
    {
        return $this->listResponse($request, Normalizer::list($this->bankAccounts->findAll()));
    }

    /** GET /bank-accounts/{id}/ledger?period=monat|jahr|jahre&date=Y-m-d */
    public function ledger(ApiRequest $request): ApiResponse
    {
        $account = $this->bankAccounts->findById($request->param('id')) ?? throw ApiException::notFound('Konto wurde nicht gefunden.');
        $ledger = $this->ask(new GetAccountLedger($account->id, AccountPeriod::fromQuery($request->query('period'), $request->query('date'))));
        $euro = static fn (?int $cents): ?float => $cents !== null ? $cents / 100 : null;

        return ApiResponse::json([
            'data' => array_map(static fn (array $row) => Normalizer::normalize($row['transaction']) + [
                'amount' => $row['transaction']->amountCents / 100,
                'balance_after' => $euro($row['balanceCents']),
            ], $ledger->rows),
            'meta' => [
                'account' => Normalizer::normalize($account),
                'period' => $ledger->period->mode,
                'from' => $ledger->period->from->format('Y-m-d'),
                'to' => $ledger->period->to->format('Y-m-d'),
                'start_balance' => $euro($ledger->startBalanceCents),
                'end_balance' => $euro($ledger->endBalanceCents),
                'income' => $euro($ledger->incomeCents),
                'expense' => $euro($ledger->expenseCents),
                'current_balance' => $euro($ledger->currentBalanceCents),
            ],
        ]);
    }

    // --- Purchase orders ---------------------------------------------------

    public function purchaseOrders(ApiRequest $request): ApiResponse
    {
        $result = $this->ask(new ListPurchaseOrders(page: 1, pageSize: 1_000_000));
        $items = [];
        foreach ($result->items as $entry) {
            $items[] = Normalizer::normalize($entry->order ?? $entry) + (isset($entry->supplier) ? ['supplier' => $this->supplierRef($entry->supplier)] : []);
        }

        return $this->listResponse($request, $items);
    }

    public function purchaseOrder(ApiRequest $request): ApiResponse
    {
        return ApiResponse::data($this->purchaseOrderDetail($request->param('id')));
    }

    public function storePurchaseOrder(ApiRequest $request): ApiResponse
    {
        $id = $this->run(CreatePurchaseOrder::class, $request);

        return $this->created($this->purchaseOrderDetail((string) $id));
    }

    public function updatePurchaseOrder(ApiRequest $request): ApiResponse
    {
        $id = $request->param('id');
        $detail = $this->loadPurchaseOrder($id);
        $current = CommandFactory::props($detail->order) + ['note' => $detail->order->note ?? '', 'items' => $this->purchaseOrderItemRows($detail)];
        $this->run(UpdatePurchaseOrder::class, $request, ['id' => $id], $current);

        return ApiResponse::data($this->purchaseOrderDetail($id));
    }

    public function destroyPurchaseOrder(ApiRequest $request): ApiResponse
    {
        $this->loadPurchaseOrder($request->param('id'));
        $this->dispatch(new DeletePurchaseOrder($request->param('id')));

        return ApiResponse::noContent();
    }

    public function markPurchaseOrderOrdered(ApiRequest $request): ApiResponse
    {
        $this->loadPurchaseOrder($request->param('id'));
        $this->dispatch(new MarkPurchaseOrderOrdered($request->param('id')));

        return ApiResponse::data($this->purchaseOrderDetail($request->param('id')));
    }

    public function cancelPurchaseOrder(ApiRequest $request): ApiResponse
    {
        $this->loadPurchaseOrder($request->param('id'));
        $this->dispatch(new MarkPurchaseOrderCancelled($request->param('id')));

        return ApiResponse::data($this->purchaseOrderDetail($request->param('id')));
    }

    /** POST /purchase-order-items/{id}/receive {quantity} — goods receipt. */
    public function receivePurchaseOrderItem(ApiRequest $request): ApiResponse
    {
        $this->run(ReceivePurchaseOrderItem::class, $request, ['purchaseOrderItemId' => $request->param('id')]);

        return ApiResponse::json(['ok' => true]);
    }

    // --- helpers -----------------------------------------------------------

    private function loadReceipt(string $id): ReceiptDetail
    {
        return $this->ask(new GetReceipt($id));
    }

    /** @return array<string, mixed> */
    private function receiptDetail(string $id): array
    {
        $detail = $this->loadReceipt($id);

        return $this->receiptToArray($detail->receipt, $detail->supplier) + [
            'inventory_numbers' => Normalizer::normalize($detail->inventoryNumbers),
        ];
    }

    /** @return array<string, mixed> */
    private function receiptToArray(object $receipt, ?object $supplier): array
    {
        return Normalizer::normalize($receipt, ['storedFilename']) + [
            'supplier' => $this->supplierRef($supplier),
            'file_url' => '/api/v1/receipts/' . $receipt->id . '/file',
        ];
    }

    private function loadPurchaseOrder(string $id): PurchaseOrderDetail
    {
        return $this->ask(new GetPurchaseOrder($id));
    }

    /** @return array<string, mixed> */
    private function purchaseOrderDetail(string $id): array
    {
        $detail = $this->loadPurchaseOrder($id);

        return Normalizer::normalize($detail->order) + [
            'supplier' => $this->supplierRef($detail->supplier),
            'items' => array_map(static fn ($entry) => Normalizer::normalize($entry->item) + [
                'article' => ['id' => $entry->article->id, 'name' => $entry->article->name],
            ], $detail->items),
        ];
    }

    /** @return list<array{article_id: string, quantity: string, unit_price: string, target_type: string}> */
    private function purchaseOrderItemRows(PurchaseOrderDetail $detail): array
    {
        return array_values(array_map(static fn ($entry) => [
            'article_id' => $entry->item->articleId,
            'quantity' => (string) $entry->item->orderedQuantity,
            'unit_price' => $entry->item->unitPrice,
            'target_type' => $entry->item->targetType->value,
        ], $detail->items));
    }

    /** @return array{id: string, name: string}|null */
    private function supplierRef(?object $supplier): ?array
    {
        return $supplier !== null ? ['id' => $supplier->id, 'name' => $supplier->name] : null;
    }
}
