<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

use App\Application\Customer\Query\GetCustomer;
use App\Application\Query\QueryBus;

/**
 * Vorbelegung des Kunden in den Neu-Formularen (Veranstaltung, Angebot,
 * Auftragsbestätigung, Rechnung) über "?kunde=<Kunden-ID>" — genutzt von den
 * Schnellaktionen in der Kundenliste. Liefert ein $old-Array im selben
 * Format wie nach einem fehlgeschlagenen Absenden, damit die Formulare den
 * Kunden ohne weitere Sonderlogik als ausgewählt anzeigen. Eine leere oder
 * unbekannte ID wird ignoriert (leeres Formular wie bisher).
 */
final class CustomerPrefill
{
    /** @return array<string, string> */
    public static function old(Request $request, QueryBus $queries): array
    {
        $customerId = trim((string) ($request->query('kunde') ?? ''));
        if ($customerId === '') {
            return [];
        }
        try {
            $customer = $queries->ask(new GetCustomer($customerId));
        } catch (\Throwable) {
            return [];
        }

        return ['customer_id' => $customer->id];
    }
}
