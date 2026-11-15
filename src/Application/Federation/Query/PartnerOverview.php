<?php

declare(strict_types=1);

namespace App\Application\Federation\Query;

use App\Domain\Federation\PartnerInstance;
use App\Domain\Federation\PartnerInvitation;

final class PartnerOverview
{
    /**
     * @param PartnerInstance[] $partners
     * @param PartnerInvitation[] $invitations
     */
    public function __construct(
        public readonly array $partners,
        public readonly array $invitations,
        public readonly string $baseUrl,
        public readonly bool $baseUrlConfigured,
        public readonly int $pendingIncomingRequests,
    ) {
    }
}
