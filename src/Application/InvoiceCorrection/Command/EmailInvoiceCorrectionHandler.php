<?php

declare(strict_types=1);

namespace App\Application\InvoiceCorrection\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Application\InvoiceCorrection\Query\GetInvoiceCorrection;
use App\Application\InvoiceCorrection\Query\GetInvoiceCorrectionHandler;
use App\Application\InvoiceCorrection\Query\InvoiceCorrectionDetail;
use App\Application\Mail\EmailTemplateRenderer;
use App\Domain\Exception\ConflictException;
use App\Domain\Exception\ValidationException;
use App\Domain\InvoiceCorrection\InvoiceCorrectionRepositoryInterface;
use App\Domain\Mail\EmailTemplateKey;
use App\Domain\Mail\MailCategory;
use App\Infrastructure\Mail\MailAttachment;
use App\Infrastructure\Mail\MailerInterface;
use App\Infrastructure\InvoiceCorrection\InvoiceCorrectionDocuments;
use DateTimeImmutable;

use function App\Infrastructure\Http\e;

/**
 * Verschickt eine ausgestellte Rechnungskorrektur bzw. Stornorechnung als
 * PDF und E-Rechnung (XRechnung-XML) per E-Mail (Vorlage "Rechnungskorrektur / Stornorechnung per E-Mail",
 * analog EmailDunningNoticeHandler) und vermerkt Zeitpunkt und Empfänger.
 * Entwürfe werden nicht verschickt – sie haben noch keine Nummer.
 */
final class EmailInvoiceCorrectionHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly GetInvoiceCorrectionHandler $getCorrection,
        private readonly InvoiceCorrectionDocuments $documents,
        private readonly MailerInterface $mailer,
        private readonly EmailTemplateRenderer $templateRenderer,
        private readonly InvoiceCorrectionRepositoryInterface $corrections,
    ) {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof EmailInvoiceCorrection);

        $recipient = trim($command->recipientEmail);
        if ($recipient === '' || filter_var($recipient, FILTER_VALIDATE_EMAIL) === false) {
            throw new ValidationException(['recipient_email' => 'Bitte eine gültige E-Mail-Adresse angeben.']);
        }

        $detail = $this->getCorrection->handle(new GetInvoiceCorrection($command->id));
        $correction = $detail->correction;
        assert($correction !== null);
        if ($correction->isDraft()) {
            throw new ConflictException('Bitte die Rechnungskorrektur zuerst ausstellen – Entwürfe können nicht verschickt werden.');
        }

        [$pdfFilename, $pdf] = $this->documents->pdf($detail);
        [$xmlFilename, $xml] = $this->documents->xml($detail);

        $rendered = $this->templateRenderer->render(EmailTemplateKey::InvoiceCorrection, $this->variables($detail, $command->note));
        // PDF und E-Rechnung (XRechnung-XML), wie beim Rechnungsversand.
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
            MailCategory::InvoiceCorrection,
        );

        $this->corrections->markSent($correction->id, new DateTimeImmutable(), $recipient);

        return null;
    }

    /** @return array<string, string> */
    private function variables(InvoiceCorrectionDetail $detail, ?string $note): array
    {
        $correction = $detail->correction;
        $invoice = $detail->invoice->invoice;
        $customer = $detail->invoice->customer;
        $note = $note !== null ? trim($note) : '';

        return [
            'customer_name' => $customer !== null ? ', ' . e($customer->displayName()) : '',
            'document_title' => e($correction->type->label()),
            'correction_number' => e((string) $correction->correctionNumber),
            'invoice_number' => e($invoice->invoiceNumber ?? ''),
            'invoice_date' => e($invoice->invoiceDate?->format('d.m.Y') ?? ''),
            'reason' => nl2br(e($correction->reason)),
            'amount' => number_format($correction->grossTotal(), 2, ',', '.') . ' €',
            'payment_line' => e($this->documents->paymentText($detail)),
            'note_html' => $note !== '' ? '<p><strong>Anmerkung:</strong><br>' . nl2br(e($note)) . '</p>' : '',
        ];
    }
}
