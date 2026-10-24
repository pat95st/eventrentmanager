<?php

declare(strict_types=1);

namespace App\Application\Rental\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Application\NoticeDocument\Query\GetNoticeDocumentsForArticleIds;
use App\Application\NoticeDocument\Query\GetNoticeDocumentsForArticleIdsHandler;
use App\Application\Rental\Query\GetRental;
use App\Application\Rental\Query\GetRentalHandler;
use App\Domain\Exception\ValidationException;
use App\Domain\Mail\MailCategory;
use App\Infrastructure\FileStorage\LocalFileStorage;
use App\Infrastructure\Mail\MailAttachment;
use App\Infrastructure\Mail\MailerInterface;

use function App\Infrastructure\Http\e;

/**
 * Verschickt alle Hinweisdokumente, die den gebuchten Artikeln dieser
 * Veranstaltung zugeordnet sind (siehe GetNoticeDocumentsForArticleIdsHandler),
 * gesammelt als Anhänge in einer einzigen E-Mail an den Kunden. Bewusst eine
 * einfache Text-Mail ohne das Vorlagensystem (EmailTemplateRenderer) — anders
 * als Angebot/Rechnung/Packliste ist der Mailtext hier austauschbarer Inhalt
 * ohne Formatierungsbedarf.
 */
final class SendNoticeDocumentsToCustomerHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly GetRentalHandler $getRentalHandler,
        private readonly GetNoticeDocumentsForArticleIdsHandler $getNoticeDocumentsForArticleIdsHandler,
        private readonly LocalFileStorage $fileStorage,
        private readonly MailerInterface $mailer,
    ) {
    }

    public function handle(CommandInterface $command): void
    {
        assert($command instanceof SendNoticeDocumentsToCustomer);

        $recipient = trim($command->recipientEmail);
        if ($recipient === '' || filter_var($recipient, FILTER_VALIDATE_EMAIL) === false) {
            throw new ValidationException(['recipient_email' => 'Bitte eine gültige E-Mail-Adresse angeben.']);
        }

        // Wirft NotFoundException, falls die Veranstaltung nicht existiert.
        $detail = $this->getRentalHandler->handle(new GetRental($command->rentalId));

        $articleIds = array_values(array_unique(array_map(
            static fn ($entry) => $entry->article->id,
            $detail->items,
        )));

        $documents = $this->getNoticeDocumentsForArticleIdsHandler->handle(new GetNoticeDocumentsForArticleIds($articleIds));

        if ($documents === []) {
            throw new ValidationException(['documents' => 'Für diese Veranstaltung sind keine Hinweisdokumente hinterlegt.']);
        }

        $attachments = array_map(
            fn ($document) => new MailAttachment(
                $document->originalFilename,
                $document->mimeType,
                file_get_contents($this->fileStorage->absolutePath('hinweisdokumente', $document->storedFilename)),
            ),
            $documents,
        );

        $documentNames = implode(', ', array_map(static fn ($document) => $document->name, $documents));

        $subject = 'Hinweisdokumente zu Ihrer Veranstaltung „' . $detail->rental->eventName . '“';
        $textBody = "Guten Tag,\n\n"
            . "anbei erhalten Sie die Hinweisdokumente zu den für Ihre Veranstaltung gebuchten Artikeln:\n"
            . $documentNames . "\n\n"
            . "Mit freundlichen Grüßen";

        $this->mailer->send($recipient, $subject, $textBody, $attachments, null, MailCategory::NoticeDocuments);
    }
}
