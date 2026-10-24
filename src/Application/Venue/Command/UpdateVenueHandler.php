<?php

declare(strict_types=1);

namespace App\Application\Venue\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Domain\Venue\VenueRepositoryInterface;
use App\Infrastructure\Geocoding\GeocoderInterface;
use DateTimeImmutable;

final class UpdateVenueHandler implements CommandHandlerInterface
{
    use ValidatesVenueInput;

    public function __construct(
        private readonly VenueRepositoryInterface $venues,
        private readonly GeocoderInterface $geocoder,
    ) {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof UpdateVenue);

        $venue = $this->venues->findById($command->id);
        if ($venue === null) {
            throw new NotFoundException("Veranstaltungsort \"{$command->id}\" wurde nicht gefunden.");
        }

        [$errors, $data] = $this->validateVenueInput(
            $command->name,
            $command->street,
            $command->postalCode,
            $command->city,
            $command->country,
            $command->notes,
            $command->caretakerName,
            $command->caretakerPhone,
        );

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $addressChanged = $venue->street !== $data['street']
            || $venue->postalCode !== $data['postalCode']
            || $venue->city !== $data['city']
            || $venue->country !== $data['country'];

        if ($addressChanged) {
            // Adresse hat sich geändert — neu geokodieren (best effort, siehe
            // CreateVenueHandler). Bleiben die Koordinaten diesmal aus, gehen
            // eventuell zuvor ermittelte (jetzt veraltete) Koordinaten verloren,
            // was hier bewusst in Kauf genommen wird, statt eine falsche alte
            // Position weiter anzuzeigen.
            $coordinates = $this->geocoder->geocode($data['street'], $data['postalCode'], $data['city'], $data['country']);
            $venue->latitude = $coordinates[0] ?? null;
            $venue->longitude = $coordinates[1] ?? null;
        }

        $venue->name = $data['name'];
        $venue->street = $data['street'];
        $venue->postalCode = $data['postalCode'];
        $venue->city = $data['city'];
        $venue->country = $data['country'];
        $venue->notes = $data['notes'];
        $venue->caretakerName = $data['caretakerName'];
        $venue->caretakerPhone = $data['caretakerPhone'];
        $venue->updatedAt = new DateTimeImmutable();

        $this->venues->save($venue);

        return null;
    }
}
