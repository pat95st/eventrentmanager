<?php

declare(strict_types=1);

namespace App\Application\Command;

/**
 * Marker-Interface für alle Commands (schreibende Absicht, CQRS-"C"-Seite).
 * Ein Command beschreibt WAS geschehen soll, niemals WIE es umgesetzt wird.
 */
interface CommandInterface
{
}
