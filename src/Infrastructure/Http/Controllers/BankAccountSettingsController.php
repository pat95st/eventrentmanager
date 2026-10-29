<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\Command\CommandBus;
use App\Application\Query\QueryBus;
use App\Application\Settings\Command\CreateBankAccount;
use App\Application\Settings\Command\DeleteBankAccount;
use App\Application\Settings\Command\UpdateBankAccount;
use App\Application\Settings\Query\ListBankAccounts;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Domain\Settings\BankAccount;
use App\Infrastructure\Http\Flash;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;
use App\Infrastructure\Http\View;

use function App\Infrastructure\Http\t;

/**
 * Verwaltung mehrerer Bankverbindungen (Nutzerwunsch, Migration 105) —
 * Liste + Formular auf einer Seite, analog DunningLevelController.
 */
final class BankAccountSettingsController
{
    public function __construct(
        private readonly CommandBus $commands,
        private readonly QueryBus $queries,
    ) {
    }

    public function index(Request $request): void
    {
        $this->render(null, [], []);
    }

    public function edit(Request $request): void
    {
        $account = $this->findAccount($request->param('id'));
        if ($account === null) {
            Response::notFound('Diese Bankverbindung wurde nicht gefunden.');

            return;
        }

        $this->render($account, [], []);
    }

    public function store(Request $request): void
    {
        $old = $this->oldFromRequest($request);

        try {
            $this->commands->dispatch(new CreateBankAccount($old['label'], $old['account_holder'], $old['bank_name'], $old['iban'], $old['bic'], $old['is_default'] === '1'));
            Flash::success(t('settings.bank_accounts.flash.created'));
            Response::redirect('/einstellungen/bankverbindungen');
        } catch (ValidationException $e) {
            $this->render(null, $e->errors(), $old);
        }
    }

    public function update(Request $request): void
    {
        $id = $request->param('id');
        $old = $this->oldFromRequest($request);

        try {
            $this->commands->dispatch(new UpdateBankAccount($id, $old['label'], $old['account_holder'], $old['bank_name'], $old['iban'], $old['bic'], $old['is_default'] === '1'));
            Flash::success(t('settings.bank_accounts.flash.updated'));
            Response::redirect('/einstellungen/bankverbindungen');
        } catch (ValidationException $e) {
            $this->render($this->findAccount($id), $e->errors(), $old);
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());
        }
    }

    public function destroy(Request $request): void
    {
        try {
            $this->commands->dispatch(new DeleteBankAccount($request->param('id')));
            Flash::success(t('settings.bank_accounts.flash.deleted'));
        } catch (NotFoundException $e) {
            Flash::error($e->getMessage());
        }

        Response::redirect('/einstellungen/bankverbindungen');
    }

    /** @return array{label: string, account_holder: string, bank_name: string, iban: string, bic: string, is_default: string} */
    private function oldFromRequest(Request $request): array
    {
        return [
            'label' => $request->input('label', '') ?? '',
            'account_holder' => $request->input('account_holder', '') ?? '',
            'bank_name' => $request->input('bank_name', '') ?? '',
            'iban' => $request->input('iban', '') ?? '',
            'bic' => $request->input('bic', '') ?? '',
            'is_default' => $request->input('is_default') === '1' ? '1' : '0',
        ];
    }

    private function findAccount(?string $id): ?BankAccount
    {
        foreach ($this->queries->ask(new ListBankAccounts()) as $account) {
            if ($account->id === $id) {
                return $account;
            }
        }

        return null;
    }

    /** @param array<string, string> $errors @param array<string, mixed> $old */
    private function render(?BankAccount $editing, array $errors, array $old): void
    {
        echo View::page('settings/bank-accounts/index', [
            'accounts' => $this->queries->ask(new ListBankAccounts()),
            'editing' => $editing,
            'errors' => $errors,
            'old' => $old,
        ], activeNav: 'settings-bankverbindungen', title: t('settings.bank_accounts.title'));
    }
}
