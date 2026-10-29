<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\Command\CommandBus;
use App\Application\Query\QueryBus;
use App\Application\Settings\Command\CreatePaymentMethod;
use App\Application\Settings\Command\DeletePaymentMethod;
use App\Application\Settings\Command\UpdatePaymentMethod;
use App\Application\Settings\Query\ListPaymentMethods;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Domain\Settings\PaymentMethod;
use App\Infrastructure\Http\Flash;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;
use App\Infrastructure\Http\View;

use function App\Infrastructure\Http\t;

/**
 * Verwaltung der Zahlungsarten mit Zahlungsziel (Nutzerwunsch, Migration
 * 106) — Liste + Formular auf einer Seite, analog DunningLevelController.
 */
final class PaymentMethodSettingsController
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
        $method = $this->findMethod($request->param('id'));
        if ($method === null) {
            Response::notFound('Diese Zahlungsart wurde nicht gefunden.');

            return;
        }

        $this->render($method, [], []);
    }

    public function store(Request $request): void
    {
        $old = $this->oldFromRequest($request);

        try {
            $this->commands->dispatch(new CreatePaymentMethod($old['name'], $old['payment_term_days'], $old['note_text'], $old['is_default'] === '1'));
            Flash::success(t('settings.payment_methods.flash.created'));
            Response::redirect('/einstellungen/zahlungsarten');
        } catch (ValidationException $e) {
            $this->render(null, $e->errors(), $old);
        }
    }

    public function update(Request $request): void
    {
        $id = $request->param('id');
        $old = $this->oldFromRequest($request);

        try {
            $this->commands->dispatch(new UpdatePaymentMethod($id, $old['name'], $old['payment_term_days'], $old['note_text'], $old['is_default'] === '1'));
            Flash::success(t('settings.payment_methods.flash.updated'));
            Response::redirect('/einstellungen/zahlungsarten');
        } catch (ValidationException $e) {
            $this->render($this->findMethod($id), $e->errors(), $old);
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());
        }
    }

    public function destroy(Request $request): void
    {
        try {
            $this->commands->dispatch(new DeletePaymentMethod($request->param('id')));
            Flash::success(t('settings.payment_methods.flash.deleted'));
        } catch (NotFoundException $e) {
            Flash::error($e->getMessage());
        }

        Response::redirect('/einstellungen/zahlungsarten');
    }

    /** @return array{name: string, payment_term_days: string, note_text: string, is_default: string} */
    private function oldFromRequest(Request $request): array
    {
        return [
            'name' => $request->input('name', '') ?? '',
            'payment_term_days' => $request->input('payment_term_days', '') ?? '',
            'note_text' => $request->input('note_text', '') ?? '',
            'is_default' => $request->input('is_default') === '1' ? '1' : '0',
        ];
    }

    private function findMethod(?string $id): ?PaymentMethod
    {
        foreach ($this->queries->ask(new ListPaymentMethods()) as $method) {
            if ($method->id === $id) {
                return $method;
            }
        }

        return null;
    }

    /** @param array<string, string> $errors @param array<string, mixed> $old */
    private function render(?PaymentMethod $editing, array $errors, array $old): void
    {
        echo View::page('settings/payment-methods/index', [
            'methods' => $this->queries->ask(new ListPaymentMethods()),
            'editing' => $editing,
            'errors' => $errors,
            'old' => $old,
        ], activeNav: 'settings-zahlungsarten', title: t('settings.payment_methods.title'));
    }
}
