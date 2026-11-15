<?php

declare(strict_types=1);

namespace App\Application\Federation;

use App\Domain\Federation\PartnerProfile;

/** Information about this installation that is shared with partners. */
interface LocalInstanceInterface
{
    /** Publicly reachable base URL, e.g. "https://verleih.example.de". */
    public function baseUrl(): string;

    /** false = base URL derived from the current request (settings › integrations not filled). */
    public function hasConfiguredBaseUrl(): bool;

    public function name(): string;

    public function profile(): PartnerProfile;
}
