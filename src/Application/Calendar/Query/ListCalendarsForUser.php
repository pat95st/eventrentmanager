<?php

declare(strict_types=1);

namespace App\Application\Calendar\Query;

use App\Application\Query\QueryInterface;

/**
 * Die für den CalDAV-Kalenderpfad eines Users sichtbaren Kalender: aktiviert
 * UND (User hat Administration-Recht ODER explizit gewährten Zugriff), siehe
 * ListCalendarsForUserHandler. Zentraler Use Case, den sowohl der
 * CalDAV-Server (CalDavController) als auch — falls künftig gebraucht — das
 * Frontend nutzen kann.
 */
final class ListCalendarsForUser implements QueryInterface
{
    public function __construct(public readonly string $userId)
    {
    }
}
