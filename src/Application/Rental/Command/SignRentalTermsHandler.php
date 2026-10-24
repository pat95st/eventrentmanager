<?php

declare(strict_types=1);

namespace App\Application\Rental\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Domain\Rental\RentalRepositoryInterface;
use App\Infrastructure\FileStorage\LocalFileStorage;
use DateTimeImmutable;

final class SignRentalTermsHandler implements CommandHandlerInterface
{
    private const SUB_DIRECTORY = 'rental-terms-signatures';

    public function __construct(
        private readonly RentalRepositoryInterface $rentals,
        private readonly LocalFileStorage $fileStorage,
    ) {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof SignRentalTerms);

        $rental = $this->rentals->findById($command->rentalId);
        if ($rental === null) {
            throw new NotFoundException("Vermietung \"{$command->rentalId}\" wurde nicht gefunden.");
        }

        // "data:image/png;base64,iVBORw0..." -> nur der Base64-Teil nach dem
        // Komma; ein leeres Canvas (Nutzer hat nicht unterschrieben) liefert
        // zwar ebenfalls eine technisch gültige, aber inhaltsleere PNG-Data-URL
        // — das lässt sich browserseitig nicht zuverlässig unterscheiden,
        // weshalb hier nur auf "überhaupt etwas Dekodierbares vorhanden" statt
        // auf "Canvas tatsächlich bemalt" geprüft wird.
        $commaPosition = strpos($command->signatureDataUrl, ',');
        $base64 = $commaPosition !== false ? substr($command->signatureDataUrl, $commaPosition + 1) : '';
        $bytes = $base64 !== '' ? base64_decode($base64, true) : false;

        if (!str_starts_with($command->signatureDataUrl, 'data:image/png;base64,') || $bytes === false || $bytes === '') {
            throw new ValidationException(['signature' => 'Es wurde keine gültige Unterschrift übermittelt. Bitte unterschreiben Sie erneut.']);
        }

        $stored = $this->fileStorage->storeRawBytes(
            $bytes,
            self::SUB_DIRECTORY,
            $rental->id,
            'signature.png',
            'image/png',
        );

        // Eine erneute Unterschrift (z. B. nach einem Fehler) überschreibt
        // bewusst die vorherige Datei (storeRawBytes() nutzt denselben
        // Dateinamen je Vermietung, siehe self::SUB_DIRECTORY) — es wird nur
        // der jeweils aktuellste Signatur-Zeitpunkt gespeichert, kein
        // Verlauf mehrerer Unterschriften je Vermietung (siehe Klassen-Doc
        // von Rental::$termsSignedAt).
        $rental->termsSignedAt = new DateTimeImmutable();
        $rental->termsSignatureFile = $stored['storedFilename'];
        $rental->updatedAt = new DateTimeImmutable();

        $this->rentals->save($rental);

        return null;
    }
}
