<?php

declare(strict_types=1);

namespace App\Application\Rental\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Application\Mail\EmailTemplateRenderer;
use App\Application\Rental\Query\GetRental;
use App\Application\Rental\Query\GetRentalHandler;
use App\Application\Rental\Query\RentalDetail;
use App\Domain\Exception\ValidationException;
use App\Domain\Mail\EmailTemplateKey;
use App\Domain\Mail\MailCategory;
use App\Domain\DocumentStorage\DocumentType;
use App\Domain\Settings\CompanySettingsRepositoryInterface;
use App\Infrastructure\DocumentStorage\DocumentStoragePusher;
use App\Infrastructure\Mail\MailAttachment;
use App\Infrastructure\Mail\MailerInterface;
use App\Infrastructure\Pdf\PackingListPdfGenerator;

use function App\Infrastructure\Http\e;

/**
 * Verschickt die Packliste einer Veranstaltung (siehe PackingListPdfGenerator,
 * bereits für den Download unter RentalController::exportPackingListPdf
 * genutzt) als PDF-Anhang per E-Mail an eine frei wählbare Empfängeradresse
 * (i. d. R. die des verknüpften Kunden, siehe rentals/show.php).
 *
 * Nutzt GetRentalHandler direkt statt über den QueryBus zu gehen — kein
 * Handler im Projekt hängt bislang von den Bus-Klassen ab, dafür aber
 * durchaus von anderen Application-Bausteinen (siehe z. B. die
 * Repository-Injektion in GetRentalHandler selbst), daher folgt dies
 * derselben Linie.
 */
final class EmailPackingListPdfHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly GetRentalHandler $getRentalHandler,
        private readonly CompanySettingsRepositoryInterface $companySettings,
        private readonly \App\Domain\Settings\PdfLayout\PdfLayoutSettingsRepositoryInterface $pdfLayoutSettings,
        private readonly PackingListPdfGenerator $pdfGenerator,
        private readonly MailerInterface $mailer,
        private readonly EmailTemplateRenderer $templateRenderer,
        private readonly DocumentStoragePusher $documentStoragePusher,
    ) {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof EmailPackingListPdf);

        $recipient = trim($command->recipientEmail);
        if ($recipient === '' || filter_var($recipient, FILTER_VALIDATE_EMAIL) === false) {
            throw new ValidationException(['recipient_email' => 'Bitte eine gültige E-Mail-Adresse angeben.']);
        }

        // Wirft NotFoundException, falls die Veranstaltung nicht existiert —
        // wird vom Controller genauso behandelt wie bei den übrigen Aktionen.
        $detail = $this->getRentalHandler->handle(new GetRental($command->rentalId));
        $companySettings = $this->companySettings->get();
        $layout = $this->pdfLayoutSettings->getForDocumentType(\App\Domain\Settings\PdfLayout\PdfDocumentType::PackingList);

        $pdf = $this->pdfGenerator->generate($detail, $companySettings, $layout);
        $filename = 'Packliste-' . $this->sanitizeFilename(
            $detail->rental->eventName !== '' ? $detail->rental->eventName : $detail->rental->id
        ) . '.pdf';

        // Synchroner Push auf konfigurierte Ziellaufwerke (siehe
        // DocumentStoragePusher) — Packlisten sind keine Entwurfs-Dokumente,
        // daher immer gepusht (kein Status-Konzept nötig).
        $this->documentStoragePusher->push(
            DocumentType::PackingList,
            $detail->rental->id,
            $pdf,
            $filename,
            ['{number}' => $detail->rental->id],
        );

        $variables = $this->buildTemplateVariables($command, $detail);
        $rendered = $this->templateRenderer->render(EmailTemplateKey::PackingListPdf, $variables);

        $attachments = [new MailAttachment($filename, 'application/pdf', $pdf)];
        if ($rendered->logoAttachment !== null) {
            $attachments[] = $rendered->logoAttachment;
        }

        // Wirft MailerException bei SMTP-Fehlern (nicht konfiguriert,
        // Verbindung/Auth fehlgeschlagen, …) — bewusst nicht hier gefangen,
        // der Controller übersetzt das in eine Flash-Fehlermeldung.
        $this->mailer->send(
            $recipient,
            $rendered->subject,
            $rendered->textBody,
            $attachments,
            $rendered->htmlBody,
            MailCategory::PackingListPdf,
        );

        return null;
    }

    /** @return array<string, string> */
    private function buildTemplateVariables(EmailPackingListPdf $command, RentalDetail $detail): array
    {
        $rental = $detail->rental;

        $locationLine = $rental->eventLocation !== null && trim($rental->eventLocation) !== ''
            ? 'Ort: ' . e($rental->eventLocation) . '<br>'
            : '';

        $note = $command->note !== null ? trim($command->note) : '';
        $noteHtml = $note !== ''
            ? '<p><strong>Anmerkung:</strong><br>' . nl2br(e($note)) . '</p>'
            : '';

        return [
            'event_name' => e($rental->eventName),
            'customer_name' => e($detail->customer->displayName()),
            'location_line' => $locationLine,
            'date_range' => $rental->startDate->format('d.m.Y') . ' - ' . $rental->endDate->format('d.m.Y'),
            'note_html' => $noteHtml,
        ];
    }

    /** Erzeugt einen für einen Dateinamen sicheren Bestandteil (keine Pfad-/Sonderzeichen). */
    private function sanitizeFilename(string $value): string
    {
        $normalized = preg_replace('/[^A-Za-z0-9_\-]+/', '-', $value) ?? '';
        $normalized = trim($normalized, '-');

        return $normalized !== '' ? $normalized : 'Veranstaltung';
    }
}
