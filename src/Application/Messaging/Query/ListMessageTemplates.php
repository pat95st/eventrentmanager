<?php

declare(strict_types=1);

namespace App\Application\Messaging\Query;

use App\Application\Query\QueryInterface;
use App\Domain\Messaging\MessagingChannel;

final class ListMessageTemplates implements QueryInterface
{
    public function __construct(public readonly MessagingChannel $channel)
    {
    }
}
