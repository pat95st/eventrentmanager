<?php

declare(strict_types=1);

namespace App\Application\Rental;

use App\Domain\Exception\ValidationException;
use App\Domain\Settings\IntegrationBaseSettingsRepositoryInterface;

/**
 * Baut die absolute, von außen (für den Kunden) erreichbare URL des
 * Kunden-Checklisten-Links aus der firmenweit konfigurierten Basis-URL
 * (Einstellungen > Integrationen) — exakt dasselbe Vorgehen wie
 * App\Application\Article\Channel\ArticleChannelUrlBuilder für die
 * öffentlichen Produktlinks, aus demselben Grund: ein Mailversand aus
 * bin/send-rental-reminders.php läuft ohne HTTP-Request und kennt daher
 * weder Host noch Schema der aktuellen Anfrage.
 */
final class RentalCustomerChecklistUrlBuilder
{
    public function __construct(private readonly IntegrationBaseSettingsRepositoryInterface $baseSettings)
    {
    }

    /** @throws ValidationException wenn keine Basis-URL hinterlegt ist */
    public function url(string $token): string
    {
        $baseUrl = $this->baseSettings->get()->baseUrl;
        if ($baseUrl === null || $baseUrl === '') {
            throw new ValidationException([
                'base_url' => 'Es ist keine Basis-URL dieser Installation hinterlegt (siehe Einstellungen > Integrationen) — ohne sie kann kein von außen erreichbarer Kunden-Checklisten-Link gebaut werden.',
            ]);
        }

        return rtrim($baseUrl, '/') . '/kundencheckliste/' . $token;
    }
}
