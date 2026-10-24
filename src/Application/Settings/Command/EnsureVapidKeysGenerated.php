<?php

declare(strict_types=1);

namespace App\Application\Settings\Command;

use App\Application\Command\CommandInterface;

/**
 * Erzeugt (falls noch nicht geschehen) einmalig das anwendungsweite
 * VAPID-Schlüsselpaar, siehe EnsureVapidKeysGeneratedHandler. Bewusst ein
 * Command (kein Query) — auch wenn dieser Anwendungsfall häufig als reines
 * "Lesen" der Push-Einstellungsseite auftritt, KANN er dabei Zustand
 * verändern (Schlüsselerzeugung), was Queries in dieser Anwendung laut
 * Konvention niemals dürfen.
 */
final class EnsureVapidKeysGenerated implements CommandInterface
{
}
