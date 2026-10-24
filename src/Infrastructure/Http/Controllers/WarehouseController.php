<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\Command\CommandBus;
use App\Application\Query\QueryBus;
use App\Application\Warehouse\Command\CreateWarehouse;
use App\Application\Warehouse\Command\DeleteWarehouse;
use App\Application\Warehouse\Command\UpdateWarehouse;
use App\Application\Warehouse\Query\GetWarehouse;
use App\Application\Warehouse\Query\ListWarehouses;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Domain\Warehouse\Warehouse;
use App\Infrastructure\Http\Auth\CurrentUser;
use App\Infrastructure\Http\Flash;
use App\Infrastructure\Http\Pagination;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;
use App\Infrastructure\Http\View;
use function App\Infrastructure\Http\t;

final class WarehouseController
{
    public function __construct(
        private readonly CommandBus $commands,
        private readonly QueryBus $queries,
    ) {
    }

    public function index(Request $request): void
    {
        $search = $request->query('q');
        $page = Pagination::page($request);
        $pageSize = Pagination::pageSize($request, CurrentUser::get());

        $result = $this->queries->ask(new ListWarehouses($search, $page, $pageSize));

        $viewData = [
            'warehouses' => $result->items,
            'search' => $search ?? '',
            'page' => $result->page,
            'pageSize' => $result->pageSize,
            'totalCount' => $result->totalCount,
            'totalPages' => $result->totalPages(),
            'paginationBaseUrl' => '/lagerorte',
            'paginationQuery' => array_filter([
                'q' => $search ?? '',
            ], static fn ($value) => $value !== ''),
        ];

        if (Pagination::isAjax($request)) {
            echo View::render('warehouses/_table', $viewData);

            return;
        }

        echo View::page('warehouses/index', $viewData, activeNav: 'warehouses', title: t('warehouses.title'));
    }

    public function create(Request $request): void
    {
        $this->renderForm(null, [], []);
    }

    public function store(Request $request): void
    {
        $command = new CreateWarehouse(
            name: $request->input('name', '') ?? '',
            street: $request->input('street', '') ?? '',
            postalCode: $request->input('postal_code', '') ?? '',
            city: $request->input('city', '') ?? '',
            country: $this->nullIfEmpty($request->input('country')),
            notes: $this->nullIfEmpty($request->input('notes')),
        );

        try {
            $this->commands->dispatch($command);
            Flash::success(t('warehouses.flash.created'));
            Response::redirect('/lagerorte');
        } catch (ValidationException $e) {
            $this->renderForm(null, $e->errors(), $this->oldFromCommand($command));
        }
    }

    public function edit(Request $request): void
    {
        $id = $request->param('id');

        try {
            $warehouse = $this->queries->ask(new GetWarehouse($id));
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());

            return;
        }

        $this->renderForm($warehouse, [], [], $warehouse->id);
    }

    public function update(Request $request): void
    {
        $id = $request->param('id');

        $command = new UpdateWarehouse(
            id: $id,
            name: $request->input('name', '') ?? '',
            street: $request->input('street', '') ?? '',
            postalCode: $request->input('postal_code', '') ?? '',
            city: $request->input('city', '') ?? '',
            country: $this->nullIfEmpty($request->input('country')),
            notes: $this->nullIfEmpty($request->input('notes')),
        );

        try {
            $this->commands->dispatch($command);
            Flash::success(t('warehouses.flash.updated'));
            Response::redirect('/lagerorte');
        } catch (ValidationException $e) {
            $warehouse = $this->queries->ask(new GetWarehouse($id));
            $this->renderForm($warehouse, $e->errors(), $this->oldFromCommand($command), $id);
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());
        }
    }

    public function destroy(Request $request): void
    {
        $id = $request->param('id');

        try {
            $this->commands->dispatch(new DeleteWarehouse($id));
            Flash::success(t('warehouses.flash.deleted'));
        } catch (NotFoundException $e) {
            Flash::error($e->getMessage());
        }

        Response::redirect('/lagerorte');
    }

    /**
     * @param array<string, string> $errors
     * @param array<string, mixed> $old
     */
    private function renderForm(
        ?Warehouse $warehouse,
        array $errors,
        array $old,
        ?string $editingId = null,
    ): void {
        echo View::page('warehouses/form', [
            'warehouse' => $warehouse,
            'errors' => $errors,
            'old' => $old,
            'editingId' => $editingId,
        ], activeNav: 'warehouses', title: $warehouse !== null ? t('warehouses.edit') : t('warehouses.new'));
    }

    /** @return array<string, mixed> */
    private function oldFromCommand(CreateWarehouse|UpdateWarehouse $command): array
    {
        return [
            'name' => $command->name,
            'street' => $command->street,
            'postal_code' => $command->postalCode,
            'city' => $command->city,
            'country' => $command->country,
            'notes' => $command->notes,
        ];
    }

    private function nullIfEmpty(?string $value): ?string
    {
        return $value === null || $value === '' ? null : $value;
    }
}
