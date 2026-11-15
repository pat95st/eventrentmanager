<?php

declare(strict_types=1);

namespace App\Domain\Venue;

/** Whether haze/fog machines may be used at a venue. */
enum FogPolicy: string
{
    case Allowed = 'allowed';
    case NotAllowed = 'not_allowed';
    /** Only after consultation — the fire alarm system (BMZ) is temporarily disabled. */
    case OnRequest = 'on_request';

    /** Translation key of the label (see resources/lang). */
    public function labelKey(): string
    {
        return 'venues.fog.' . $this->value;
    }
}
