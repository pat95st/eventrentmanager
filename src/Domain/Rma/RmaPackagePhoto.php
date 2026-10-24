<?php

declare(strict_types=1);

namespace App\Domain\Rma;

use DateTimeImmutable;

/**
 * Ein Foto des Pakets/der Verpackung, das VOR dem Versand einer externen
 * RMA aufgenommen wurde (Beweissicherung für den Zustand bei Abgabe, z. B.
 * bei Transportschäden-Diskussionen mit dem Versanddienstleister oder dem
 * Empfänger). Bewusst als eigene, schlanke Entität analog RmaDocument
 * gehalten statt dort mit hineinzunehmen, weil hier nur Bilder (kein PDF/
 * .eml) erlaubt sind und die Oberfläche sie als eigenen Abschnitt
 * "Fotos vor dem Versand" mit Bild-Vorschau statt Dateiliste anzeigt.
 */
final class RmaPackagePhoto
{
    public function __construct(
        public readonly string $id,
        public readonly string $rmaId,
        public readonly string $originalFilename,
        public readonly string $storedFilename, // Dateiname auf der Platte, unter storage/uploads/rma-versandfotos/<rmaId>/
        public readonly string $mimeType,
        public readonly int $sizeBytes,
        public readonly DateTimeImmutable $uploadedAt,
    ) {
    }
}
