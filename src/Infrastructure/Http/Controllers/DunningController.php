<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\Command\CommandBus;
use App\Application\Dunning\Command\CreateDunningNotice;
use App\Application\Dunning\Command\EmailDunningNotice;
use App\Application\Dunning\Query\GetDunningNotice;
use App\Application\Query\QueryBus;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Infrastructure\Http\Flash;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;
use App\Infrastructure\Mail\MailerException;
use App\Infrastructure\Pdf\DunningPdfGenerator;
use App\Application\Settings\Query\GetCompanySettings;

use function App\Infrastructure\Http\t;

/**
 * Mahnungs-Aktionen zu einer konkreten Rechnung (Erstellen, PDF-Download,
 * E-Mail-Versand) — bewusst ein eigener Controller statt Erweiterung von
 * InvoiceController, da eine Mahnung fachlich ein eigenständiges Dokument
 * ist (siehe DunningNotice-Klassendoc), auch wenn sie immer im Kontext
 * einer Rechnung entsteht.
 */
final class DunningController
{
    public function __construct(
        private readonly CommandBus $commands,
        private readonly QueryBus $queries,
        private readonly DunningPdfGenerator $pdfGenerator,
    ) {
    }

    public function store(Request $request): void
    {
        $invoiceId = $request->param('id');
        $dunningLevelId = $request->input('dunning_level_id', '') ?? '';

        try {
            $this->commands->dispatch(new CreateDunningNotice($invoiceId, $dunningLevelId));
            Flash::success(t('invoices.show.dunning_flash_created'));
        } catch (ValidationException $e) {
            Flash::error(implode(' ', $e->errors()));
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());

            return;
        }

        Response::redirect('/rechnungen/' . $invoiceId);
    }

    public function exportPdf(Request $request): void
    {
        $id = $request->param('dunningId');

        try {
            $detail = $this->queries->ask(new GetDunningNotice($id));
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());

            return;
        }

        $companySettings = $this->queries->ask(new GetCompanySettings());
        $pdf = $this->pdfGenerator->generate($detail, $companySettings);

        Response::download($pdf, 'Mahnung-' . $detail->notice->dunningNumber . '.pdf', 'application/pdf');
    }

    public function emailPdf(Request $request): void
    {
        $id = $request->param('dunningId');
        $recipient = trim($request->input('recipient_email', '') ?? '');

        try {
            $detail = $this->queries->ask(new GetDunningNotice($id));
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());

            return;
        }

        try {
            $this->commands->dispatch(new EmailDunningNotice($id, $recipient));
            Flash::success(t('invoices.show.dunning_flash_emailed', ['recipient' => $recipient]));
        } catch (ValidationException $e) {
            Flash::error(implode(' ', $e->errors()));
        } catch (MailerException $e) {
            Flash::error(t('invoices.flash.email_failed', ['error' => $e->getMessage()]));
        }

        Response::redirect('/rechnungen/' . $detail->notice->invoiceId);
    }
}
