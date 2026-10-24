<?php

declare(strict_types=1);

namespace App\Application\Common;

use App\Domain\Venue\VenueRepositoryInterface;

/**
 * Löst ein einzeiliges Veranstaltungsort-Freitextfeld (siehe z. B.
 * rentals/form.php bzw. offers/form.php, Datalist mit den gespeicherten
 * Veranstaltungsorten) gegen die Stammdaten auf: stimmt der eingegebene Text
 * bei exakter (Groß-/Kleinschreibung ignorierender) Übereinstimmung mit
 * einem gespeicherten Veranstaltungsort überein, wird diesem zugeordnet
 * (venueId gesetzt, Name auf den kanonischen Namen normalisiert); ansonsten
 * bleibt es reiner Freitext ohne venueId. Bleibt das Feld leer, werden
 * sowohl der Name als auch venueId null.
 *
 * Ursprünglich Teil von ValidatesRentalInput (Vermietungen); als eigener
 * Baustein extrahiert, damit Angebote (Offer) dieselbe Logik nutzen können,
 * ohne sie zu duplizieren.
 */
final class VenueResolver
{
    /** @return array{0: ?string, 1: ?string} [eventLocation, venueId] */
    public static function resolve(VenueRepositoryInterface $venues, string $rawEventLocation): array
    {
        $eventLocation = trim($rawEventLocation);

        if ($eventLocation === '') {
            return [null, null];
        }

        $matchedVenue = $venues->findByName($eventLocation);
        if ($matchedVenue !== null) {
            return [$matchedVenue->name, $matchedVenue->id];
        }

        return [$eventLocation, null];
    }
}
