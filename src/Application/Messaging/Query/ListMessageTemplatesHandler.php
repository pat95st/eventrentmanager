<?php

declare(strict_types=1);

namespace App\Application\Messaging\Query;

use App\Application\Query\QueryHandlerInterface;
use App\Application\Query\QueryInterface;
use App\Domain\Messaging\MessageTemplateRepositoryInterface;

final class ListMessageTemplatesHandler implements QueryHandlerInterface
{
    public function __construct(private readonly MessageTemplateRepositoryInterface $templates)
    {
    }

    public function handle(QueryInterface $query): mixed
    {
        assert($query instanceof ListMessageTemplates);

        return $this->templates->getAllForChannel($query->channel);
    }
}
