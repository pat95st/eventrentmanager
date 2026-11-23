<?php

declare(strict_types=1);

namespace App\Domain\Logbook;

/**
 * Start or destination of a trip as a snapshot (migration 184): label,
 * address and coordinates at the time of saving.
 */
final class LogbookLocation
{
    public function __construct(
        public readonly LogbookLocationType $type,
        public readonly ?string $refId,
        public readonly string $label,
        public readonly ?string $address = null,
        public readonly ?float $latitude = null,
        public readonly ?float $longitude = null,
    ) {
    }

    public function hasCoordinates(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }

    /** "Label, address" for lists and exports. */
    public function display(): string
    {
        return $this->address !== null && $this->address !== '' && $this->address !== $this->label
            ? $this->label . ', ' . $this->address
            : $this->label;
    }

    /** Same place (type and reference, or the same custom text)? */
    public function samePlaceAs(self $other): bool
    {
        return $this->type === $other->type && $this->refId === $other->refId
            && ($this->type->needsReference() || $this->label === $other->label);
    }
}
