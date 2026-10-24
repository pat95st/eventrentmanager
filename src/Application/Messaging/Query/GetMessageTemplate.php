<?php

declare(strict_types=1);

namespace App\Application\Messaging\Query;

use App\Application\Query\QueryInterface;
use App\Domain\Messaging\MessageTemplateKey;
use App\Domain\Messaging\MessagingChannel;

final class GetMessageTemplate implements QueryInterface
{
    public function __construct(
        public readonly MessagingChannel $channel,
        public readonly MessageTemplateKey $key,
    ) {
    }
}
