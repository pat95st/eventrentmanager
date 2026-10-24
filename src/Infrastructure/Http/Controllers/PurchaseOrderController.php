<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\Article\Query\ListArticles;
use App\Application\Command\CommandBus;
use App\Application\Purchase\Command\CreatePurchaseOrder;
use App\Application\Purchase\Command\DeletePurchaseOrder;
use App\Application\Purchase\Command\MarkPurchaseOrderCancelled;
use App\Application\Purchase\Command\MarkPurchaseOrderOrdered;
use App\Application\Purchase\Command\ReceivePurchaseOrderItem;
use App\Application\Purchase\Command\UpdatePurchaseOrder;
use App\Application\Purchase\Query\GetPurchaseOrder;
use App\Application\Purchase\Query\ListPurchaseOrders;
use App\Application\Query\QueryBus;
use App\Domain\Exception\ConflictException;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Domain\Purchase\PurchaseOrder;
use App\Domain\Purchase\PurchaseOrderStatus;
use App\Domain\Supplier\SupplierRepositoryInterface;
use App\Infrastructure\Http\Auth\CurrentUser;
use App\Infrastructure\Http\Flash;
use App\Infrastructure\Http\Pagination;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;
use App\Infrastructure\Http\View;
use function App\Infrastructure\Http\t;

final class PurchaseOrderController
{
    public function __construct(
        private readonly CommandBus $commands,
        private readonly QueryBus $queries,
        private readonly SupplierRepositoryInterface $suppliers,
    ) {
    }

    public function index(Request $request): void
    {
        $search = $request->query('q');
        $statusRaw = $this->nullIfEmpty($request->query('status'));
        $status = $statusRaw !== null ? PurchaseOrderStatus::tryFrom($statusRaw) : null;
        $page = Pagination::page($request);
        $pageSize = Pagination::pageSize($request, CurrentUser::get());

        $result = $this->queries->ask(new ListPurchaseOrders($search, $status, $page, $pageSize));

        $viewData = [
            'orders' => $result->items,
            'search' => $search ?? '',
            'selectedStatus' => $status,
            'statuses' => PurchaseOrderStatus::cases(),
            'page' => $result->page,
            'pageSize' => $result->pageSize,
            'totalCount' => $result->totalCount,
            'totalPages' => $result->totalPages(),
            'paginationBaseUrl' => '/bestellungen',
            'paginationQuery' => array_filter([
                'q' => $search ?? '',
                'status' => $statusRaw ?? '',
            ], static fn ($value) => $value !== ''),
        ];

        if (Pagination::isAjax($request)) {
            echo View::render('purchase_orders/_table', $viewData);

            return;
        }

        echo View::page('purchase_orders/index', $viewData, activeNav: 'purchase_orders', title: t('purchase_orders.title'));
    }

    public function create(Request $request): void
    {
        $this->renderForm(null, [], []);
    }

    public function store(Request $request): void
    {
        $command = new CreatePurchaseOrder(
            supplierId: $request->input('supplier_id', '') ?? '',
            note: $request->input('note', '') ?? '',
            items: $this->itemsFromRequest($request),
        );

        try {
            $id = $this->commands->dispatch($command);
            Flash::success(t('purchase_orders.flash.created'));
            Response::redirect('/bestellungen/' . $id);
        } catch (ValidationException $e) {
            $this->renderForm(null, $e->errors(), $this->oldFromCommand($command));
        }
    }

    public function show(Request $request): void
    {
        $id = $request->param('id');

        try {
            $detail = $this->queries->ask(new GetPurchaseOrder($id));
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());

            return;
        }

        echo View::page('purchase_orders/show', [
            'detail' => $detail,
        ], activeNav: 'purchase_orders', title: t('purchase_orders.page_title.detail'));
    }

    public function edit(Request $request): void
    {
        $id = $request->param('id');

        try {
            $detail = $this->queries->ask(new GetPurchaseOrder($id));
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());

            return;
        }

        if (!$detail->order->isEditable()) {
            Flash::error(t('purchase_orders.flash.locked'));
            Response::redirect('/bestellungen/' . $id);

            return;
        }

        $this->renderForm($detail->order, [], [], $detail->order->id);
    }

    public function update(Request $request): void
    {
        $id = $request->param('id');

        $command = new UpdatePurchaseOrder(
            id: $id,
            supplierId: $request->input('supplier_id', '') ?? '',
            note: $request->input('note', '') ?? '',
            items: $this->itemsFromRequest($request),
        );

        try {
            $this->commands->dispatch($command);
            Flash::success(t('purchase_orders.flash.updated'));
            Response::redirect('/bestellungen/' . $id);
        } catch (ValidationException $e) {
            $detail = $this->queries->ask(new GetPurchaseOrder($id));
            $this->renderForm($detail->order, $e->errors(), $this->oldFromCommand($command), $id);
        } catch (ConflictException $e) {
            Flash::error($e->getMessage());
            Response::redirect('/bestellungen/' . $id);
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());
        }
    }

    public function destroy(Request $request): void
    {
        $id = $request->param('id');

        try {
            $this->commands->dispatch(new DeletePurchaseOrder($id));
            Flash::success(t('purchase_orders.flash.deleted'));
            Response::redirect('/bestellungen');
        } catch (ConflictException $e) {
            Flash::error($e->getMessage());
            Response::redirect('/bestellungen/' . $id);
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());
        }
    }

    public function markOrdered(Request $request): void
    {
        $id = $request->param('id');

        try {
            $this->commands->dispatch(new MarkPurchaseOrderOrdered($id));
            Flash::success(t('purchase_orders.flash.marked_ordered'));
        } catch (ValidationException $e) {
            Flash::error(implode(' ', $e->errors()));
        } catch (ConflictException $e) {
            Flash::error($e->getMessage());
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());

            return;
        }

        Response::redirect('/bestellungen/' . $id);
    }

    public function markCancelled(Request $request): void
    {
        $id = $request->param('id');

        try {
            $this->commands->dispatch(new MarkPurchaseOrderCancelled($id));
            Flash::success(t('purchase_orders.flash.marked_cancelled'));
        } catch (ConflictException $e) {
            Flash::error($e->getMessage());
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());

            return;
        }

        Response::redirect('/bestellungen/' . $id);
    }

    /** Erfasst einen (Teil-)Wareneingang für EINE Bestellposition (siehe ReceivePurchaseOrderItemHandler) — die Positions-ID kommt als Formularfeld, nicht als eigenes Routensegment. */
    public function receiveItem(Request $request): void
    {
        $id = $request->param('id');
        $itemId = $request->input('item_id', '') ?? '';
        $quantity = $request->input('quantity', '') ?? '';

        try {
            $this->commands->dispatch(new ReceivePurchaseOrderItem($itemId, $quantity));
            Flash::success(t('purchase_orders.flash.received'));
        } catch (ValidationException $e) {
            Flash::error(implode(' ', $e->errors()));
        } catch (ConflictException $e) {
            Flash::error($e->getMessage());
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());

            return;
        }

        Response::redirect('/bestellungen/' . $id);
    }

    /** @param array<string, string> $errors @param array<string, mixed> $old */
    private function renderForm(?PurchaseOrder $order, array $errors, array $old, ?string $editingId = null): void
    {
        $suppliers = $this->suppliers->findAll();
        $articles = $this->queries->ask(new ListArticles(onlyActive: true))->items;

        $existingItems = [];
        if ($order !== null) {
            $detail = $this->queries->ask(new GetPurchaseOrder($order->id));
            foreach ($detail->items as $entry) {
                $existingItems[] = [
                    'article_id' => $entry->item->articleId,
                    'target_type' => $entry->item->targetType->value,
                    'quantity' => $entry->item->orderedQuantity,
                    'unit_price' => $entry->item->unitPrice,
                ];
            }
        }

        echo View::page('purchase_orders/form', [
            'order' => $order,
            'suppliers' => $suppliers,
            'articles' => $articles,
            'errors' => $errors,
            'old' => $old,
            'editingId' => $editingId,
            'existingItems' => $existingItems,
        ], activeNav: 'purchase_orders', title: $order !== null ? t('purchase_orders.edit') : t('purchase_orders.new'));
    }

    /** @return array<int, array{article_id: string, target_type: string, quantity: string, unit_price: string}> */
    private function itemsFromRequest(Request $request): array
    {
        $articleIds = $request->post['article_id'] ?? [];
        $targetTypes = $request->post['target_type'] ?? [];
        $quantities = $request->post['quantity'] ?? [];
        $unitPrices = $request->post['unit_price'] ?? [];

        if (!is_array($articleIds)) {
            return [];
        }

        $items = [];
        foreach ($articleIds as $index => $articleId) {
            $items[] = [
                'article_id' => (string) $articleId,
                'target_type' => (string) ($targetTypes[$index] ?? ''),
                'quantity' => (string) ($quantities[$index] ?? ''),
                'unit_price' => (string) ($unitPrices[$index] ?? ''),
            ];
        }

        return $items;
    }

    /** @return array<string, mixed> */
    private function oldFromCommand(CreatePurchaseOrder|UpdatePurchaseOrder $command): array
    {
        return [
            'supplier_id' => $command->supplierId,
            'note' => $command->note,
            'items' => $command->items,
        ];
    }

    private function nullIfEmpty(?string $value): ?string
    {
        return $value === null || $value === '' ? null : $value;
    }
}
