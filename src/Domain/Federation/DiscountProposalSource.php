<?php

declare(strict_types=1);

namespace App\Domain\Federation;

/** Who made a pending discount proposal. */
enum DiscountProposalSource: string
{
    case Us = 'us';
    case Them = 'them';
}
