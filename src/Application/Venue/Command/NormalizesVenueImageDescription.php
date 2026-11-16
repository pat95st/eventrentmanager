<?php

declare(strict_types=1);

namespace App\Application\Venue\Command;

use App\Domain\Exception\ValidationException;
use App\Domain\Venue\VenueImage;

/** Shared description handling for AddVenueImageHandler and UpdateVenueImageHandler. */
trait NormalizesVenueImageDescription
{
    private function normalizeDescription(?string $description): ?string
    {
        $description = $description !== null ? trim(str_replace("\r\n", "\n", $description)) : '';
        if ($description === '') {
            return null;
        }
        if (mb_strlen($description) > VenueImage::MAX_DESCRIPTION_LENGTH) {
            throw new ValidationException(['description' => 'Die Beschreibung darf höchstens ' . VenueImage::MAX_DESCRIPTION_LENGTH . ' Zeichen lang sein.']);
        }

        return $description;
    }
}
