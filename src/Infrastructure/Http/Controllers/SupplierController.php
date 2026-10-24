<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\Command\CommandBus;
use App\Application\Query\QueryBus;
use App\Application\Supplier\Command\CreateSupplier;
use App\Application\Supplier\Command\DeleteSupplier;
use App\Application\Supplier\Command\UpdateSupplier;
use App\Application\Supplier\Query\GetSupplier;
use App\Application\Supplier\Query\ListSuppliers;
use App\Domain\Exception\ConflictException;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Domain\Supplier\Supplier;
use App\Infrastructure\Http\Auth\CurrentUser;
use App\Infrastructure\Http\Flash;
use App\Infrastructure\Http\Pagination;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;
use App\Infrastructure\Http\View;

final class SupplierController
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

        $result = $this->queries->ask(new ListSuppliers($search, $page, $pageSize));

        $viewData = [
            'suppliers' => $result->items,
            'search' => $search ?? '',
            'page' => $result->page,
            'pageSize' => $result->pageSize,
            'totalCount' => $result->totalCount,
            'totalPages' => $result->totalPages(),
            'paginationBaseUrl' => '/lieferanten',
            'paginationQuery' => array_filter([
                'q' => $search ?? '',
            ], static fn ($value) => $value !== ''),
        ];

        if (Pagination::isAjax($request)) {
            echo View::render('suppliers/_table', $viewData);

            return;
        }

        echo View::page('suppliers/index', $viewData, activeNav: 'suppliers', title: 'Lieferanten');
    }

    public function create(Request $request): void
    {
        $this->renderForm(null, [], []);
    }

    public function store(Request $request): void
    {
        $command = new CreateSupplier(
            name: $request->input('name', '') ?? '',
            type: $request->input('type', '') ?? '',
            street: $request->input('street', '') ?? '',
            postalCode: $request->input('postal_code', '') ?? '',
            city: $request->input('city', '') ?? '',
            phone: $this->nullIfEmpty($request->input('phone')),
            email: $this->nullIfEmpty($request->input('email')),
            customerNumber: $this->nullIfEmpty($request->input('customer_number')),
        );

        try {
            $this->commands->dispatch($command);
            Flash::success('Lieferant wurde angelegt.');
            Response::redirect('/lieferanten');
        } catch (ValidationException $e) {
            $this->renderForm(null, $e->errors(), $this->oldFromCommand($command));
        }
    }

    public function edit(Request $request): void
    {
        $id = $request->param('id');

        try {
            $supplier = $this->queries->ask(new GetSupplier($id));
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());

            return;
        }

        $this->renderForm($supplier, [], [], $supplier->id);
    }

    public function update(Request $request): void
    {
        $id = $request->param('id');

        $command = new UpdateSupplier(
            id: $id,
            name: $request->input('name', '') ?? '',
            type: $request->input('type', '') ?? '',
            street: $request->input('street', '') ?? '',
            postalCode: $request->input('postal_code', '') ?? '',
            city: $request->input('city', '') ?? '',
            phone: $this->nullIfEmpty($request->input('phone')),
            email: $this->nullIfEmpty($request->input('email')),
            customerNumber: $this->nullIfEmpty($request->input('customer_number')),
        );

        try {
            $this->commands->dispatch($command);
            Flash::success('Lieferant wurde aktualisiert.');
            Response::redirect('/lieferanten');
        } catch (ValidationException $e) {
            $supplier = $this->queries->ask(new GetSupplier($id));
            $this->renderForm($supplier, $e->errors(), $this->oldFromCommand($command), $id);
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());
        }
    }

    public function destroy(Request $request): void
    {
        $id = $request->param('id');

        try {
            $this->commands->dispatch(new DeleteSupplier($id));
            Flash::success('Lieferant wurde gelöscht.');
        } catch (ConflictException|NotFoundException $e) {
            Flash::error($e->getMessage());
        }

        Response::redirect('/lieferanten');
    }

    /** @param array<string, string> $errors @param array<string, mixed> $old */
    private function renderForm(?Supplier $supplier, array $errors, array $old, ?string $editingId = null): void
    {
        echo View::page('suppliers/form', [
            'supplier' => $supplier,
            'errors' => $errors,
            'old' => $old,
            'editingId' => $editingId,
        ], activeNav: 'suppliers', title: $supplier !== null ? 'Lieferant bearbeiten' : 'Neuer Lieferant');
    }

    /** @return array<string, mixed> */
    private function oldFromCommand(CreateSupplier|UpdateSupplier $command): array
    {
        return [
            'name' => $command->name,
            'type' => $command->type,
            'street' => $command->street,
            'postal_code' => $command->postalCode,
            'city' => $command->city,
            'phone' => $command->phone,
            'email' => $command->email,
            'customer_number' => $command->customerNumber,
        ];
    }

    private function nullIfEmpty(?string $value): ?string
    {
        return $value === null || $value === '' ? null : $value;
    }
}
