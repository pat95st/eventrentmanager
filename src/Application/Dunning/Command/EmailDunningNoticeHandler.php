<?php

declare(strict_types=1);

namespace App\Application\Dunning\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Application\Dunning\Query\DunningNoticeDetail;
use App\Application\Dunning\Query\GetDunningNotice;
use App\Application\Dunning\Query\GetDunningNoticeHandler;
use App\Application\Mail\EmailTemplateRenderer;
use App\Domain\Dunning\DunningNoticeRepositoryInterface;
use App\Domain\Exception\ValidationException;
use App\Domain\Mail\EmailTemplateKey;
use App\Domain\Mail\MailCategory;
use App\Domain\DocumentStorage\DocumentType;
use App\Domain\Settings\CompanySettingsRepositoryInterface;
use App\Infrastructure\DocumentStorage\DocumentStoragePusher;
use App\Infrastructure\Mail\MailAttachment;
use App\Infrastructure\Mail\MailerInterface;
use App\Infrastructure\Pdf\DunningPdfGenerator;
use DateTimeImmutable;

use function App\Infrastructure\Http\e;

/**
 * Verschickt eine Mahnung per E-Mail — analog EmailInvoicePdfHandler, aber
 * mit nur einem Anhang (dem Mahnungs-PDF, kein XRechnung-XML, da eine
 * Mahnung keine eigenständige Rechnung ist). Markiert die Mahnung nach
 * erfolgreichem Versand als verschickt (sentAt), siehe
 * DunningNoticeRepositoryInterface::markSent().
 */
final class EmailDunningNoticeHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly GetDunningNoticeHandler $getDunningNoticeHandler,
        private readonly CompanySettingsRepositoryInterface $companySettings,
        private readonly \App\Domain\Settings\PdfLayout\PdfLayoutSettingsRepositoryInterface $pdfLayoutSettings,
        private readonly DunningPdfGenerator $pdfGenerator,
        private readonly MailerInterface $mailer,
        private readonly EmailTemplateRenderer $templateRenderer,
        private readonly DunningNoticeRepositoryInterface $dunningNotices,
        private readonly DocumentStoragePusher $documentStoragePusher,
    ) {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof EmailDunningNotice);

        $recipient = trim($command->recipientEmail);
        if ($recipient === '' || filter_var($recipient, FILTER_VALIDATE_EMAIL) === false) {
            throw new ValidationException(['recipient_email' => 'Bitte eine gültige E-Mail-Adresse angeben.']);
        }

        $detail = $this->getDunningNoticeHandler->handle(new GetDunningNotice($command->dunningNoticeId));

        $companySettings = $this->companySettings->get();
        $layout = $this->pdfLayoutSettings->getForDocumentType(\App\Domain\Settings\PdfLayout\PdfDocumentType::Dunning);
        $pdf = $this->pdfGenerator->generate($detail, $companySettings, $layout);

        $pdfFilename = 'Mahnung-' . $detail->notice->dunningNumber . '.pdf';

        // Synchroner Push auf konfigurierte Ziellaufwerke (siehe
        // DocumentStoragePusher-Klassendoc) — analog EmailInvoicePdfHandler.
        // Ein fehlgeschlagener Push kann den Mailversand niemals verhindern.
        $this->documentStoragePusher->push(
            DocumentType::DunningNotice,
            $detail->notice->id,
            $pdf,
            $pdfFilename,
            ['{number}' => $detail->notice->dunningNumber],
        );

        $variables = $this->buildTemplateVariables($detail);
        $rendered = $this->templateRenderer->render(EmailTemplateKey::DunningNotice, $variables);

        $attachments = [
            new MailAttachment($pdfFilename, 'application/pdf', $pdf),
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
            MailCategory::DunningNotice,
        );

        $this->dunningNotices->markSent($detail->notice->id, new DateTimeImmutable());

        return null;
    }

    /** @return array<string, string> */
    private function buildTemplateVariables(DunningNoticeDetail $detail): array
    {
        $notice = $detail->notice;
        $invoice = $detail->invoice;

        $customerName = $detail->customer !== null ? ', ' . e($detail->customer->displayName()) : '';

        return [
            'customer_name' => $customerName,
            'dunning_number' => e($notice->dunningNumber),
            'dunning_level_name' => e($notice->levelName),
            'invoice_number' => e($invoice->invoiceNumber ?? ''),
            'due_date' => $invoice->dueDate !== null ? e($invoice->dueDate->format('d.m.Y')) : '',
            'total_due' => number_format($notice->totalDue(), 2, ',', '.') . ' €',
        ];
    }
}
