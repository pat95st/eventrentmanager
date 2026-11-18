<?php

declare(strict_types=1);

namespace App\Domain\Tour;

/** How additional material of a stop is occupied. */
enum TourAllocation: string
{
    case StopsOnly = 'stops_only'; // only around the affected stops, issued/returned separately
    case WholeTour = 'whole_tour'; // travels the whole tour

    public function label(): string
    {
        return match ($this) {
            self::StopsOnly => 'nur für die betroffenen Tourstops',
            self::WholeTour => 'fährt die ganze Tour mit',
        };
    }
}
