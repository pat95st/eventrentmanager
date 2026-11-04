<?php

declare(strict_types=1);

namespace App\Domain\Settings;

/**
 * Darstellung eines Hintergrunds (Migration 126): Deckkraft, Einpassung,
 * Bildausschnitt (Position) und Zoom. Gilt gleichermaßen für Bilder und Videos.
 */
final class BackgroundStyle
{
    public const FIT_COVER = 'cover';
    public const FIT_CONTAIN = 'contain';
    public const FITS = [self::FIT_COVER, self::FIT_CONTAIN];

    public const MIN_OPACITY = 5;
    public const MIN_ZOOM = 100;
    public const MAX_ZOOM = 300;

    public readonly int $opacity;
    public readonly string $fit;
    public readonly int $positionX;
    public readonly int $positionY;
    public readonly int $zoom;

    /**
     * @param int $opacity Deckkraft in Prozent (5–100)
     * @param string $fit 'cover' = Fläche füllen, 'contain' = ganz anzeigen
     * @param int $positionX Ausschnitt waagerecht in Prozent (0 = links, 100 = rechts)
     * @param int $positionY Ausschnitt senkrecht in Prozent (0 = oben, 100 = unten)
     * @param int $zoom Vergrößerung in Prozent (100–300)
     */
    public function __construct(int $opacity = 100, string $fit = self::FIT_COVER, int $positionX = 50, int $positionY = 50, int $zoom = 100)
    {
        $this->opacity = max(self::MIN_OPACITY, min(100, $opacity));
        $this->fit = in_array($fit, self::FITS, true) ? $fit : self::FIT_COVER;
        $this->positionX = max(0, min(100, $positionX));
        $this->positionY = max(0, min(100, $positionY));
        $this->zoom = max(self::MIN_ZOOM, min(self::MAX_ZOOM, $zoom));
    }

    public static function fitLabel(string $fit): string
    {
        return match ($fit) {
            self::FIT_CONTAIN => 'Ganz anzeigen',
            default => 'Fläche füllen',
        };
    }

    /** CSS-Position, z. B. "30% 50%" (background-position/object-position/transform-origin). */
    public function cssPosition(): string
    {
        return $this->positionX . '% ' . $this->positionY . '%';
    }
}
