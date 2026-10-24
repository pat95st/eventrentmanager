<?php

declare(strict_types=1);

namespace App\Application\Query;

/**
 * Marker-Interface für alle Queries (lesende Absicht, CQRS-"Q"-Seite).
 * Queries verändern niemals Zustand.
 */
interface QueryInterface
{
}
