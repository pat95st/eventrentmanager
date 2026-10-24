<?php

declare(strict_types=1);

namespace App\Application\Offer\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Application\Mail\EmailTemplateRenderer;
use App\Application\Offer\Query\GetOffer;
use App\Application\Offer\Query\GetOfferHandler;
use App\Application\Offer\Query\OfferDetail;
use App\Domain\Exception\ValidationException;
use App\Domain\Mail\EmailTemplateKey;
use App\Domain\Mail\MailCategory;
use App\Domain\Settings\CompanySettingsRepositoryInterface;
use App\Domain\Settings\LegalSettingsRepositoryInterface;
use App\Domain\DocumentStorage\DocumentType;
use App\Domain\Offer\OfferStatus;
use App\Domain\Settings\OfferPdfSettingsRepositoryInterface;
use App\Domain\Settings\PdfDesignSettingsRepositoryInterface;
use App\Infrastructure\DocumentStorage\DocumentStoragePusher;
use App\Infrastructure\Mail\MailAttachment;
use App\Infrastructure\Mail\MailerInterface;
use App\Infrastructure\Pdf\OfferPdfGenerator;

use function App\Infrastructure\Http\e;

/**
 * Verschickt ein Angebot (siehe OfferPdfGenerator, auch für den Download
 * unter OfferController::exportPdf genutzt) als PDF-Anhang per E-Mail,
 * analog zu EmailPackingListPdfHandler (Vermietungen) — nutzt dieselbe
 * Mailer-Infrastruktur (MailerInterface/SmtpMailer).
 */
final class EmailOfferPdfHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly GetOfferHandler $getOfferHandler,
        private readonly CompanySettingsRepositoryInterface $companySettings,
        private readonly LegalSettingsRepositoryInterface $legalSettings,
        private readonly OfferPdfSettingsRepositoryInterface $offerPdfSettings,
        private readonly PdfDesignSettingsRepositoryInterface $pdfDesignSettings,
        private readonly OfferPdfGenerator $pdfGenerator,
        private readonly MailerInterface $mailer,
        private readonly EmailTemplateRenderer $templateRenderer,
        private readonly DocumentStoragePusher $documentStoragePusher,
    ) {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof EmailOfferPdf);

        $recipient = trim($command->recipientEmail);
        if ($recipient === '' || filter_var($recipient, FILTER_VALIDATE_EMAIL) === false) {
            throw new ValidationException(['recipient_email' => 'Bitte eine gültige E-Mail-Adresse angeben.']);
        }

        $detail = $this->getOfferHandler->handle(new GetOffer($command->offerId));
        $companySettings = $this->companySettings->get();
        $legalSettings = $this->legalSettings->get();
        $offerPdfSettings = $this->offerPdfSettings->get();
        $pdfDesignSettings = $this->pdfDesignSettings->get();

        $pdf = $this->pdfGenerator->generate($detail, $companySettings, $legalSettings, $offerPdfSettings, $pdfDesignSettings);
        $filename = 'Angebot-' . strtoupper(substr($detail->offer->id, 0, 8)) . '.pdf';

        // Synchroner Push auf konfigurierte Ziellaufwerke (siehe
        // DocumentStoragePusher) — nur bei bereits versendeten Angeboten,
        // Entwürfe werden bewusst nicht gepusht (siehe Projektdokumentation).
        if ($detail->offer->status === OfferStatus::Sent) {
            $this->documentStoragePusher->push(
                DocumentType::Offer,
                $detail->offer->id,
                $pdf,
                $filename,
                ['{number}' => $detail->offer->id],
            );
        }

        $variables = $this->buildTemplateVariables($command, $detail);
        $rendered = $this->templateRenderer->render(EmailTemplateKey::OfferPdf, $variables);

        $attachments = [new MailAttachment($filename, 'application/pdf', $pdf)];
        if ($rendered->logoAttachment !== null) {
            $attachments[] = $rendered->logoAttachment;
        }

        $this->mailer->send(
            $recipient,
            $rendered->subject,
            $rendered->textBody,
            $attachments,
            $rendered->htmlBody,
            MailCategory::OfferPdf,
        );

        return null;
    }

    /** @return array<string, string> */
    private function buildTemplateVariables(EmailOfferPdf $command, OfferDetail $detail): array
    {
        $offer = $detail->offer;

        // Führendes ", " statt eines eigenen Platzhalters für den Namen
        // allein, damit "Guten Tag{{customer_name}}," (siehe Vorlage) sowohl
        // ohne Kunde ("Guten Tag,") als auch mit Kunde ("Guten Tag, Max
        // Mustermann,") grammatikalisch korrekt bleibt.
        $customerName = $detail->customer !== null ? ', ' . e($detail->customer->displayName()) : '';

        $locationLine = $offer->eventLocation !== null && trim($offer->eventLocation) !== ''
            ? 'Ort: ' . e($offer->eventLocation) . '<br>'
            : '';

        $validUntilLine = $offer->validUntil !== null
            ? 'Gültig bis: ' . e($offer->validUntil->format('d.m.Y'))
            : '';

        $note = $command->note !== null ? trim($command->note) : '';
        $noteHtml = $note !== ''
            ? '<p><strong>Anmerkung:</strong><br>' . nl2br(e($note)) . '</p>'
            : '';

        return [
            'customer_name' => $customerName,
            'location_line' => $locationLine,
            'date_range' => $offer->startDate->format('d.m.Y') . ' - ' . $offer->endDate->format('d.m.Y'),
            'valid_until_line' => $validUntilLine,
            'note_html' => $noteHtml,
        ];
    }
}
