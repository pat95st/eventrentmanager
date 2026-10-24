<?php

declare(strict_types=1);

namespace App\Shared;

/**
 * Minimaler UUID-v4-Generator, damit das Projekt ohne Composer-Abhängigkeiten
 * auskommt. Für hohe Lastszenarien kann später auf ramsey/uuid gewechselt werden.
 */
final class Uuid
{
    public static function v4(): string
    {
        $data = random_bytes(16);

        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40); // Version 4
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80); // Variant

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
