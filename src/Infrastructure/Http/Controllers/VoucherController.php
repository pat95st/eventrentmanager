<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\Command\CommandBus;
use App\Application\Query\QueryBus;
use App\Application\Voucher\Command\CreateVoucher;
use App\Application\Voucher\Command\DeleteVoucher;
use App\Application\Voucher\Command\UpdateVoucher;
use App\Application\Voucher\Query\GetVoucher;
use App\Application\Voucher\Query\ListVouchers;
use App\Application\Settings\Query\GetVoucherSettings;
use App\Domain\Exception\ConflictException;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Domain\Voucher\Voucher;
use App\Domain\Voucher\VoucherRedemptionRepositoryInterface;
use App\Infrastructure\Http\Auth\CurrentUser;
use App\Infrastructure\Http\Flash;
use App\Infrastructure\Http\Pagination;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;
use App\Infrastructure\Http\View;

final class VoucherController
{
    public function __construct(
        private readonly CommandBus $commands,
        private readonly QueryBus $queries,
        private readonly VoucherRedemptionRepositoryInterface $redemptions,
    ) {
    }

    public function index(Request $request): void
    {
        $search = $request->query('q');
        $page = Pagination::page($request);
        $pageSize = Pagination::pageSize($request, CurrentUser::get());

        $result = $this->queries->ask(new ListVouchers($search, $page, $pageSize));

        $voucherIds = array_map(static fn (Voucher $voucher) => $voucher->id, $result->items);
        $redemptionsByVoucherId = $this->redemptions->findByVoucherIds($voucherIds);

        $viewData = [
            'vouchers' => $result->items,
            'redemptionsByVoucherId' => $redemptionsByVoucherId,
            'search' => $search ?? '',
            'page' => $result->page,
            'pageSize' => $result->pageSize,
            'totalCount' => $result->totalCount,
            'totalPages' => $result->totalPages(),
            'paginationBaseUrl' => '/gutscheine',
            'paginationQuery' => array_filter([
                'q' => $search ?? '',
            ], static fn ($value) => $value !== ''),
        ];

        if (Pagination::isAjax($request)) {
            echo View::render('vouchers/_table', $viewData);

            return;
        }

        echo View::page('vouchers/index', $viewData, activeNav: 'vouchers', title: 'Gutscheine');
    }

    public function create(Request $request): void
    {
        $this->renderForm(null, [], []);
    }

    public function store(Request $request): void
    {
        $command = new CreateVoucher(
            codePrefix: $this->nullIfEmpty($request->input('code_prefix')),
            discountType: $request->input('discount_type', '') ?? '',
            discountValue: $request->input('discount_value', '') ?? '',
            validFrom: $this->nullIfEmpty($request->input('valid_from')),
            validUntil: $this->nullIfEmpty($request->input('valid_until')),
            notes: $this->nullIfEmpty($request->input('notes')),
        );

        try {
            $this->commands->dispatch($command);
            Flash::success('Gutschein wurde angelegt.');
            Response::redirect('/gutscheine');
        } catch (ValidationException $e) {
            $this->renderForm(null, $e->errors(), $this->oldFromCreateCommand($command));
        }
    }

    public function edit(Request $request): void
    {
        $id = $request->param('id');

        try {
            $voucher = $this->queries->ask(new GetVoucher($id));
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());

            return;
        }

        $this->renderForm($voucher, [], [], $voucher->id);
    }

    public function update(Request $request): void
    {
        $id = $request->param('id');

        $command = new UpdateVoucher(
            id: $id,
            discountType: $request->input('discount_type', '') ?? '',
            discountValue: $request->input('discount_value', '') ?? '',
            validFrom: $this->nullIfEmpty($request->input('valid_from')),
            validUntil: $this->nullIfEmpty($request->input('valid_until')),
            notes: $this->nullIfEmpty($request->input('notes')),
        );

        try {
            $this->commands->dispatch($command);
            Flash::success('Gutschein wurde aktualisiert.');
            Response::redirect('/gutscheine');
        } catch (ValidationException $e) {
            $voucher = $this->queries->ask(new GetVoucher($id));
            $this->renderForm($voucher, $e->errors(), $this->oldFromUpdateCommand($command), $id);
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());
        }
    }

    public function destroy(Request $request): void
    {
        $id = $request->param('id');

        try {
            $this->commands->dispatch(new DeleteVoucher($id));
            Flash::success('Gutschein wurde gelöscht.');
        } catch (ConflictException|NotFoundException $e) {
            Flash::error($e->getMessage());
        }

        Response::redirect('/gutscheine');
    }

    /** @param array<string, string> $errors @param array<string, mixed> $old */
    private function renderForm(?Voucher $voucher, array $errors, array $old, ?string $editingId = null): void
    {
        $settings = $this->queries->ask(new GetVoucherSettings());

        echo View::page('vouchers/form', [
            'voucher' => $voucher,
            'errors' => $errors,
            'old' => $old,
            'editingId' => $editingId,
            'defaultValidityDays' => $settings->defaultValidityDays,
        ], activeNav: 'vouchers', title: $voucher !== null ? 'Gutschein bearbeiten' : 'Neuer Gutschein');
    }

    /** @return array<string, mixed> */
    private function oldFromCreateCommand(CreateVoucher $command): array
    {
        return [
            'code_prefix' => $command->codePrefix,
            'discount_type' => $command->discountType,
            'discount_value' => $command->discountValue,
            'valid_from' => $command->validFrom,
            'valid_until' => $command->validUntil,
            'notes' => $command->notes,
        ];
    }

    /** @return array<string, mixed> */
    private function oldFromUpdateCommand(UpdateVoucher $command): array
    {
        return [
            'discount_type' => $command->discountType,
            'discount_value' => $command->discountValue,
            'valid_from' => $command->validFrom,
            'valid_until' => $command->validUntil,
            'notes' => $command->notes,
        ];
    }

    private function nullIfEmpty(?string $value): ?string
    {
        return $value === null || $value === '' ? null : $value;
    }
}
