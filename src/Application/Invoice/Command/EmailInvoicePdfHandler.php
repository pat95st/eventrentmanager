<?php

declare(strict_types=1);

namespace App\Application\Invoice\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Application\Invoice\Query\GetInvoice;
use App\Application\Invoice\Query\GetInvoiceHandler;
use App\Application\Invoice\Query\InvoiceDetail;
use App\Application\Mail\EmailTemplateRenderer;
use App\Domain\Exception\ValidationException;
use App\Domain\Mail\EmailTemplateKey;
use App\Domain\Mail\MailCategory;
use App\Domain\Settings\CompanySettingsRepositoryInterface;
use App\Domain\Settings\InvoicePdfSettingsRepositoryInterface;
use App\Domain\Settings\InvoiceSettingsRepositoryInterface;
use App\Domain\Settings\LegalSettingsRepositoryInterface;
use App\Domain\Settings\PdfDesignSettingsRepositoryInterface;
use App\Domain\DocumentStorage\DocumentType;
use App\Infrastructure\DocumentStorage\DocumentStoragePusher;
use App\Infrastructure\EInvoice\XRechnungGenerator;
use App\Infrastructure\Mail\MailAttachment;
use App\Infrastructure\Mail\MailerInterface;
use App\Infrastructure\Pdf\InvoicePdfGenerator;

use function App\Infrastructure\Http\e;

/**
 * Verschickt eine Rechnung per E-Mail — analog EmailOfferPdfHandler, aber
 * mit ZWEI Anhängen: dem Rechnungs-PDF (InvoicePdfGenerator) und dem
 * XRechnung-XML (XRechnungGenerator). Das XML wird nur angehängt, wenn die
 * Rechnung finalisiert ist (Rechnungsnummer vergeben) — bei einem Versuch,
 * eine noch nicht versendete Rechnung (Entwurf) zu mailen, wird bewusst ein
 * klarer Validierungsfehler geworfen, statt ein unvollständiges/leeres
 * E-Rechnungs-XML zu verschicken.
 */
final class EmailInvoicePdfHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly GetInvoiceHandler $getInvoiceHandler,
        private readonly CompanySettingsRepositoryInterface $companySettings,
        private readonly InvoiceSettingsRepositoryInterface $invoiceSettings,
        private readonly LegalSettingsRepositoryInterface $legalSettings,
        private readonly InvoicePdfSettingsRepositoryInterface $invoicePdfSettings,
        private readonly PdfDesignSettingsRepositoryInterface $pdfDesignSettings,
        private readonly \App\Domain\Settings\PdfLayout\PdfLayoutSettingsRepositoryInterface $pdfLayoutSettings,
        private readonly InvoicePdfGenerator $pdfGenerator,
        private readonly XRechnungGenerator $xRechnungGenerator,
        private readonly MailerInterface $mailer,
        private readonly EmailTemplateRenderer $templateRenderer,
        private readonly DocumentStoragePusher $documentStoragePusher,
    ) {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof EmailInvoicePdf);

        $recipient = trim($command->recipientEmail);
        if ($recipient === '' || filter_var($recipient, FILTER_VALIDATE_EMAIL) === false) {
            throw new ValidationException(['recipient_email' => 'Bitte eine gültige E-Mail-Adresse angeben.']);
        }

        $detail = $this->getInvoiceHandler->handle(new GetInvoice($command->invoiceId));

        if (!$detail->invoice->isFinalized()) {
            throw new ValidationException(['status' => 'Eine Rechnung im Status "Entwurf" kann nicht per E-Mail versendet werden — bitte zuerst als versendet markieren.']);
        }

        $companySettings = $this->companySettings->get();
        $invoiceSettings = $this->invoiceSettings->get();
        $legalSettings = $this->legalSettings->get();
        $invoicePdfSettings = $this->invoicePdfSettings->get();
        $pdfDesignSettings = $this->pdfDesignSettings->get();
        $layout = $this->pdfLayoutSettings->getForDocumentType(\App\Domain\Settings\PdfLayout\PdfDocumentType::Invoice);

        $pdf = $this->pdfGenerator->generate($detail, $companySettings, $invoiceSettings, $legalSettings, $invoicePdfSettings, $pdfDesignSettings, $layout);
        $xml = $this->xRechnungGenerator->generate($detail, $companySettings, $invoiceSettings);

        $pdfFilename = 'Rechnung-' . $detail->invoice->invoiceNumber . '.pdf';
        $xmlFilename = 'Rechnung-' . $detail->invoice->invoiceNumber . '.xml';

        // Synchroner Push auf konfigurierte Ziellaufwerke (siehe
        // DocumentStoragePusher) — bewusst NACH der Finalisierungs-Prüfung
        // oben, sodass nur echte (versendbare) Rechnungen gepusht werden.
        // Ein fehlgeschlagener Push kann den Mailversand niemals verhindern
        // (DocumentStoragePusher fängt jeden Fehler selbst ab).
        $invoiceNumberPlaceholder = ['{number}' => $detail->invoice->invoiceNumber ?? $detail->invoice->id];
        $this->documentStoragePusher->push(DocumentType::Invoice, $detail->invoice->id, $pdf, $pdfFilename, $invoiceNumberPlaceholder);
        $this->documentStoragePusher->push(DocumentType::Invoice, $detail->invoice->id, $xml, $xmlFilename, $invoiceNumberPlaceholder);

        $variables = $this->buildTemplateVariables($command, $detail);
        $rendered = $this->templateRenderer->render(EmailTemplateKey::InvoicePdf, $variables);

        $attachments = [
            new MailAttachment($pdfFilename, 'application/pdf', $pdf),
            new MailAttachment($xmlFilename, 'application/xml', $xml),
        ];
        if ($rendered->logoAttachment !== null) {
            $attachments[] = $rendered->logoAttachment;
        }

        $this->mailer->send(
            $recipient,
            $rendered->subject,
            $rendered->textBody,
            $attachments,
            $rendered->htmlBody,
            MailCategory::InvoicePdf,
        );

        return null;
    }

    /** @return array<string, string> */
    private function buildTemplateVariables(EmailInvoicePdf $command, InvoiceDetail $detail): array
    {
        $invoice = $detail->invoice;

        // Führendes ", " statt eines eigenen Platzhalters für den Namen
        // allein, siehe EmailOfferPdfHandler::buildTemplateVariables().
        $customerName = $detail->customer !== null ? ', ' . e($detail->customer->displayName()) : '';

        $locationLine = $invoice->eventLocation !== null && trim($invoice->eventLocation) !== ''
            ? 'Ort: ' . e($invoice->eventLocation) . '<br>'
            : '';

        $note = $command->note !== null ? trim($command->note) : '';
        $noteHtml = $note !== ''
            ? '<p><strong>Anmerkung:</strong><br>' . nl2br(e($note)) . '</p>'
            : '';

        return [
            'customer_name' => $customerName,
            'location_line' => $locationLine,
            // Siehe EmailOfferPdfHandler für die identische Begründung
            // (Zeitraum optional seit Migration 102).
            'date_range' => $invoice->startDate !== null && $invoice->endDate !== null
                ? $invoice->startDate->format('d.m.Y') . ' - ' . $invoice->endDate->format('d.m.Y')
                : 'nicht festgelegt',
            'invoice_number' => e($invoice->invoiceNumber ?? ''),
            'due_date' => $invoice->dueDate !== null ? e($invoice->dueDate->format('d.m.Y')) : '',
            'note_html' => $noteHtml,
        ];
    }
}
