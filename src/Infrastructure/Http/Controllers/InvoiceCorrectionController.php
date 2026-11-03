<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\Command\CommandBus;
use App\Application\InvoiceCorrection\Command\CreateInvoiceCorrection;
use App\Application\InvoiceCorrection\Command\DeleteInvoiceCorrection;
use App\Application\InvoiceCorrection\Command\EmailInvoiceCorrection;
use App\Application\InvoiceCorrection\Command\InvoiceCorrectionInput;
use App\Application\InvoiceCorrection\Command\IssueInvoiceCorrection;
use App\Application\InvoiceCorrection\Command\MarkInvoiceCorrectionSettled;
use App\Application\InvoiceCorrection\Command\UpdateInvoiceCorrection;
use App\Application\InvoiceCorrection\Query\GetInvoiceCorrection;
use App\Application\InvoiceCorrection\Query\GetNewInvoiceCorrection;
use App\Application\InvoiceCorrection\Query\InvoiceCorrectionDetail;
use App\Application\InvoiceCorrection\Query\ListInvoiceCorrections;
use App\Application\Query\QueryBus;
use App\Domain\Exception\ConflictException;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Domain\InvoiceCorrection\InvoiceCorrectionType;
use App\Infrastructure\Http\Flash;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;
use App\Infrastructure\Http\View;
use App\Infrastructure\Mail\MailerException;
use App\Infrastructure\InvoiceCorrection\InvoiceCorrectionDocuments;

/**
 * Rechnungskorrekturen und Stornorechnungen (Migration 121), Menü
 * Dokumente › Rechnungskorrekturen; angelegt werden sie aus der Rechnung.
 */
final class InvoiceCorrectionController
{
    public function __construct(
        private readonly CommandBus $commands,
        private readonly QueryBus $queries,
        private readonly InvoiceCorrectionDocuments $documents,
    ) {
    }

    public function index(Request $request): void
    {
        $search = $request->query('q');
        echo View::page('invoice-corrections/index', [
            'entries' => $this->queries->ask(new ListInvoiceCorrections($search)),
            'search' => $search ?? '',
        ], activeNav: 'invoice-corrections', title: 'Rechnungskorrekturen');
    }

    public function create(Request $request): void
    {
        $invoiceId = $request->param('id');
        $type = $request->query('typ') === 'storno' ? InvoiceCorrectionType::Cancellation : InvoiceCorrectionType::Partial;
        try {
            $detail = $this->queries->ask(new GetNewInvoiceCorrection($invoiceId));
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());

            return;
        }
        $this->renderForm($detail, $type, [], []);
    }

    public function store(Request $request): void
    {
        $invoiceId = $request->param('id');
        $type = $request->input('type') === InvoiceCorrectionType::Cancellation->value ? InvoiceCorrectionType::Cancellation : InvoiceCorrectionType::Partial;
        try {
            $id = $this->commands->dispatch(new CreateInvoiceCorrection($invoiceId, $type, $this->input($request)));
            Flash::success($type->label() . ' wurde als Entwurf angelegt. Prüfen und dann ausstellen.');
            Response::redirect('/rechnungskorrekturen/' . $id);
        } catch (ValidationException $e) {
            $this->renderForm($this->queries->ask(new GetNewInvoiceCorrection($invoiceId)), $type, $e->errors(), $this->old($request));
        } catch (ConflictException $e) {
            Flash::error($e->getMessage());
            Response::redirect('/rechnungen/' . $invoiceId);
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());
        }
    }

    public function show(Request $request): void
    {
        $detail = $this->load($request->param('id'));
        if ($detail === null) {
            return;
        }
        echo View::page('invoice-corrections/show', ['detail' => $detail], activeNav: 'invoice-corrections', title: $detail->correction->title());
    }

    public function edit(Request $request): void
    {
        $detail = $this->load($request->param('id'));
        if ($detail === null) {
            return;
        }
        if (!$detail->correction->isDraft()) {
            Flash::error('Eine ausgestellte Rechnungskorrektur kann nicht mehr geändert werden.');
            Response::redirect('/rechnungskorrekturen/' . $detail->correction->id);

            return;
        }
        $this->renderForm($detail, $detail->correction->type, [], []);
    }

    public function update(Request $request): void
    {
        $id = $request->param('id');
        try {
            $this->commands->dispatch(new UpdateInvoiceCorrection($id, $this->input($request)));
            Flash::success('Die Rechnungskorrektur wurde gespeichert.');
            Response::redirect('/rechnungskorrekturen/' . $id);
        } catch (ValidationException $e) {
            $detail = $this->queries->ask(new GetInvoiceCorrection($id));
            $this->renderForm($detail, $detail->correction->type, $e->errors(), $this->old($request));
        } catch (ConflictException $e) {
            Flash::error($e->getMessage());
            Response::redirect('/rechnungskorrekturen/' . $id);
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());
        }
    }

    public function issue(Request $request): void
    {
        $id = $request->param('id');
        try {
            $result = $this->commands->dispatch(new IssueInvoiceCorrection($id));
            $number = is_array($result) ? $result['number'] : (string) $result;
            $storage = is_array($result) ? ($result['storage'] ?? []) : [];
            $stored = array_filter($storage, static fn (array $r): bool => $r['success']);
            $failed = array_filter($storage, static fn (array $r): bool => !$r['success']);
            Flash::success('Die Rechnungskorrektur wurde als ' . $number . ' ausgestellt.'
                . ($stored !== [] ? ' Abgelegt in: ' . implode(', ', array_map(static fn (array $r): string => $r['targetName'], $stored)) . '.' : ''));
            if ($failed !== []) {
                Flash::error('Automatische Ablage fehlgeschlagen (' . implode('; ', array_map(static fn (array $r): string => $r['targetName'] . ': ' . $r['message'], $failed)) . '). Details unter Einstellungen › Dokumentablage.');
            }
        } catch (ConflictException | ValidationException $e) {
            Flash::error($e instanceof ValidationException ? implode(' ', $e->errors()) : $e->getMessage());
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());

            return;
        }
        Response::redirect('/rechnungskorrekturen/' . $id);
    }

    public function settle(Request $request): void
    {
        $id = $request->param('id');
        try {
            $this->commands->dispatch(new MarkInvoiceCorrectionSettled($id, $request->input('settled_at')));
            Flash::success('Erstattung bzw. Nachzahlung wurde als erledigt vermerkt.');
        } catch (ConflictException | ValidationException $e) {
            Flash::error($e instanceof ValidationException ? implode(' ', $e->errors()) : $e->getMessage());
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());

            return;
        }
        Response::redirect('/rechnungskorrekturen/' . $id);
    }

    public function destroy(Request $request): void
    {
        $id = $request->param('id');
        try {
            $invoiceId = $this->commands->dispatch(new DeleteInvoiceCorrection($id));
            Flash::success('Der Entwurf wurde gelöscht.');
            Response::redirect('/rechnungen/' . $invoiceId);
        } catch (ConflictException $e) {
            Flash::error($e->getMessage());
            Response::redirect('/rechnungskorrekturen/' . $id);
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());
        }
    }

    public function exportPdf(Request $request): void
    {
        $detail = $this->load($request->param('id'));
        if ($detail === null) {
            return;
        }
        [$name, $pdf] = $this->documents->pdf($detail);
        Response::raw($pdf, 200, 'application/pdf', ['Content-Disposition' => 'inline; filename="' . $name . '"']);
    }

    /** E-Rechnung (XRechnung-XML), nur für ausgestellte Korrekturen. */
    public function exportXRechnung(Request $request): void
    {
        $detail = $this->load($request->param('id'));
        if ($detail === null) {
            return;
        }
        try {
            [$name, $xml] = $this->documents->xml($detail);
        } catch (ConflictException $e) {
            Flash::error($e->getMessage());
            Response::redirect('/rechnungskorrekturen/' . $detail->correction->id);

            return;
        }
        Response::download($xml, $name, 'application/xml');
    }

    public function emailPdf(Request $request): void
    {
        $id = $request->param('id');
        $recipient = trim($request->input('recipient_email', '') ?? '');
        try {
            $this->commands->dispatch(new EmailInvoiceCorrection($id, $recipient, $request->input('note')));
            Flash::success('Die Rechnungskorrektur wurde an ' . $recipient . ' verschickt.');
        } catch (ValidationException $e) {
            Flash::error(implode(' ', $e->errors()));
        } catch (ConflictException $e) {
            Flash::error($e->getMessage());
        } catch (MailerException $e) {
            Flash::error('Die E-Mail konnte nicht verschickt werden: ' . $e->getMessage());
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());

            return;
        }
        Response::redirect('/rechnungskorrekturen/' . $id);
    }

    private function load(string $id): ?InvoiceCorrectionDetail
    {
        try {
            return $this->queries->ask(new GetInvoiceCorrection($id));
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());

            return null;
        }
    }

    private function input(Request $request): InvoiceCorrectionInput
    {
        $rawLines = $request->post['lines'] ?? [];

        return new InvoiceCorrectionInput(
            reason: $request->input('reason', '') ?? '',
            note: $request->input('note'),
            rawLines: is_array($rawLines) ? $rawLines : [],
        );
    }

    /** @return array<string, mixed> */
    private function old(Request $request): array
    {
        $rawLines = $request->post['lines'] ?? [];

        return [
            '_submitted' => '1',
            'reason' => (string) ($request->input('reason') ?? ''),
            'note' => (string) ($request->input('note') ?? ''),
            'lines' => is_array($rawLines) ? array_values(array_filter($rawLines, 'is_array')) : [],
        ];
    }

    /**
     * @param array<string, string> $errors
     * @param array<string, mixed> $old
     */
    private function renderForm(InvoiceCorrectionDetail $detail, InvoiceCorrectionType $type, array $errors, array $old): void
    {
        echo View::page('invoice-corrections/form', [
            'detail' => $detail,
            'type' => $type,
            'errors' => $errors,
            'old' => $old,
        ], activeNav: 'invoice-corrections', title: $type->label());
    }
}
