<?php

declare(strict_types=1);

namespace App\Application\Venue\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Exception\ValidationException;
use App\Domain\Venue\Venue;
use App\Domain\Venue\VenueRepositoryInterface;
use App\Infrastructure\Geocoding\GeocoderInterface;
use App\Shared\Uuid;
use DateTimeImmutable;

final class CreateVenueHandler implements CommandHandlerInterface
{
    use ValidatesVenueInput;

    public function __construct(
        private readonly VenueRepositoryInterface $venues,
        private readonly GeocoderInterface $geocoder,
    ) {
    }

    public function handle(CommandInterface $command): string
    {
        assert($command instanceof CreateVenue);

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

        // Geokodierung ist "best effort" — schlägt sie fehl, wird der
        // Veranstaltungsort trotzdem ohne Koordinaten gespeichert.
        $coordinates = $this->geocoder->geocode($data['street'], $data['postalCode'], $data['city'], $data['country']);

        $now = new DateTimeImmutable();
        $venue = new Venue(
            id: Uuid::v4(),
            name: $data['name'],
            street: $data['street'],
            postalCode: $data['postalCode'],
            city: $data['city'],
            country: $data['country'],
            notes: $data['notes'],
            caretakerName: $data['caretakerName'],
            caretakerPhone: $data['caretakerPhone'],
            latitude: $coordinates[0] ?? null,
            longitude: $coordinates[1] ?? null,
            createdAt: $now,
            updatedAt: $now,
        );

        $this->venues->save($venue);

        return $venue->id;
    }
}
