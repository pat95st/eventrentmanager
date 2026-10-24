<?php

declare(strict_types=1);

namespace App\Application\Rental\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Application\Mail\EmailTemplateRenderer;
use App\Application\Rental\Query\GetRental;
use App\Application\Rental\Query\GetRentalHandler;
use App\Application\Rental\Query\RentalDetail;
use App\Application\Rental\RentalCustomerChecklistTokenIssuer;
use App\Application\Rental\RentalCustomerChecklistUrlBuilder;
use App\Domain\Exception\ValidationException;
use App\Domain\Mail\EmailTemplateKey;
use App\Domain\Mail\MailCategory;
use App\Infrastructure\Mail\MailerInterface;

use function App\Infrastructure\Http\e;

/**
 * Verschickt den Kunden-Checklisten-Link per E-Mail — manuell über die
 * Veranstaltungs-Detailseite ausgelöst ODER automatisch vor dem
 * Rückgabetermin über bin/send-rental-reminders.php (siehe dort). Stellt
 * bei Bedarf selbst einen Token aus (RentalCustomerChecklistTokenIssuer,
 * forceNew: false) — ein bereits vorhandener, ggf. schon vom Kunden
 * geöffneter Link bleibt also unverändert gültig, auch wenn diese E-Mail
 * mehrfach verschickt wird.
 */
final class EmailRentalCustomerChecklistHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly GetRentalHandler $getRentalHandler,
        private readonly RentalCustomerChecklistTokenIssuer $tokenIssuer,
        private readonly RentalCustomerChecklistUrlBuilder $urlBuilder,
        private readonly MailerInterface $mailer,
        private readonly EmailTemplateRenderer $templateRenderer,
    ) {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof EmailRentalCustomerChecklist);

        $recipient = trim($command->recipientEmail);
        if ($recipient === '' || filter_var($recipient, FILTER_VALIDATE_EMAIL) === false) {
            throw new ValidationException(['recipient_email' => 'Bitte eine gültige E-Mail-Adresse angeben.']);
        }

        // Wirft NotFoundException, falls die Vermietung nicht existiert.
        $detail = $this->getRentalHandler->handle(new GetRental($command->rentalId));

        $token = $this->tokenIssuer->issue($command->rentalId);
        $link = $this->urlBuilder->url($token); // wirft ValidationException ohne konfigurierte Basis-URL

        $variables = $this->buildTemplateVariables($detail, $link);
        $rendered = $this->templateRenderer->render(EmailTemplateKey::RentalCustomerChecklist, $variables);

        $attachments = [];
        if ($rendered->logoAttachment !== null) {
            $attachments[] = $rendered->logoAttachment;
        }

        $this->mailer->send(
            $recipient,
            $rendered->subject,
            $rendered->textBody,
            $attachments,
            $rendered->htmlBody,
            MailCategory::RentalCustomerChecklist,
        );

        return null;
    }

    /** @return array<string, string> */
    private function buildTemplateVariables(RentalDetail $detail, string $link): array
    {
        $rental = $detail->rental;

        return [
            'customer_name' => e($detail->customer->displayName()),
            'event_name' => e($rental->eventName),
            'return_at' => $rental->returnAt?->format('d.m.Y, H:i \U\h\r') ?? '',
            'link' => e($link),
        ];
    }
}
