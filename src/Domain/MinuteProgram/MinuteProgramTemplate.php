<?php

declare(strict_types=1);

namespace App\Domain\MinuteProgram;

use DateTimeImmutable;

/**
 * Eine Vorlage für Minutenprogramme — speichert Programmpunkte inklusive der
 * Kopfdaten-Vorgaben Jingle/Handmikros/Headsets, NICHT aber Raum/Startzeit
 * (die sind je Veranstaltung unterschiedlich). Strukturell analog zu
 * MinuteProgram, aber bewusst eine eigene Klasse (kein konkretes
 * Minutenprogramm, sondern dessen Baustein).
 */
final class MinuteProgramTemplate
{
    public function __construct(
        public readonly string $id,
        public string $name,
        public bool $jingleBefore,
        public int $handheldMicCount,
        public int $headsetMicCount,
        public readonly DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
    ) {
    }
}
