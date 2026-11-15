<?php

declare(strict_types=1);

namespace App\Domain\Federation;

enum PartnerRequestDirection: string
{
    case Outgoing = 'outgoing'; // we asked the partner
    case Incoming = 'incoming'; // the partner asked us
}
