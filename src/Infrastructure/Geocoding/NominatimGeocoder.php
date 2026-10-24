<?php

declare(strict_types=1);

namespace App\Infrastructure\Geocoding;

/**
 * Ermittelt Breiten-/Längengrad zu einer Adresse über die öffentliche
 * OpenStreetMap-Nominatim-Suche (https://nominatim.org/release-docs/latest/api/Search/).
 *
 * Läuft serverseitig beim Anlegen/Ändern eines Veranstaltungsortes. Die
 * Geokodierung ist bewusst "best effort": schlägt sie fehl (kein Treffer,
 * kein Netzwerkzugriff, Timeout), wird der Veranstaltungsort trotzdem ohne
 * Koordinaten gespeichert — nur die Kartenanzeige entfällt dann.
 *
 * Nominatims Nutzungsbedingungen (https://operations.osmfoundation.org/policies/nominatim/)
 * verlangen einen aussagekräftigen User-Agent und max. eine Anfrage pro
 * Sekunde; für den seltenen Fall "Veranstaltungsort anlegen/ändern" ist das
 * hier unkritisch.
 */
final class NominatimGeocoder implements GeocoderInterface
{
    private const ENDPOINT = 'https://nominatim.openstreetmap.org/search';
    private const USER_AGENT = 'Materialverleih-Software/1.0 (Lagerverwaltung Veranstaltungstechnik-Verleih)';
    private const TIMEOUT_SECONDS = 5;

    /** @return array{0: float, 1: float}|null [Breitengrad, Längengrad] oder null, wenn nicht ermittelbar */
    public function geocode(string $street, string $postalCode, string $city, ?string $country): ?array
    {
        $query = trim($street . ', ' . trim($postalCode . ' ' . $city) . ($country !== null && $country !== '' ? ', ' . $country : ''));
        if ($query === '') {
            return null;
        }

        $url = self::ENDPOINT . '?' . http_build_query([
            'q' => $query,
            'format' => 'json',
            'limit' => 1,
        ]);

        if (!function_exists('curl_init')) {
            return null;
        }

        $ch = curl_init($url);
        if ($ch === false) {
            return null;
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['User-Agent: ' . self::USER_AGENT],
            CURLOPT_TIMEOUT => self::TIMEOUT_SECONDS,
            CURLOPT_CONNECTTIMEOUT => self::TIMEOUT_SECONDS,
            CURLOPT_FOLLOWLOCATION => true,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErrno = curl_errno($ch);
        curl_close($ch);

        if ($curlErrno !== 0 || $httpCode !== 200 || !is_string($response)) {
            return null;
        }

        $decoded = json_decode($response, true);
        if (!is_array($decoded) || !isset($decoded[0]['lat'], $decoded[0]['lon'])) {
            return null;
        }

        $lat = filter_var($decoded[0]['lat'], FILTER_VALIDATE_FLOAT);
        $lon = filter_var($decoded[0]['lon'], FILTER_VALIDATE_FLOAT);

        if ($lat === false || $lon === false) {
            return null;
        }

        return [$lat, $lon];
    }
}
