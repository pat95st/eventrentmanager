<?php

declare(strict_types=1);

namespace App\Application\Federation\Query;

use App\Application\Query\QueryInterface;

/** Fetch the partner's details for an invitation link before confirming. */
final class PreviewPartnerInvitation implements QueryInterface
{
    public function __construct(
        public readonly string $link,
    ) {
    }
}
