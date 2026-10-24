<?php

declare(strict_types=1);

namespace App\Application\Query;

use RuntimeException;

final class QueryBus
{
    /** @var array<class-string, QueryHandlerInterface> */
    private array $handlers = [];

    public function register(string $queryClass, QueryHandlerInterface $handler): void
    {
        $this->handlers[$queryClass] = $handler;
    }

    public function ask(QueryInterface $query): mixed
    {
        $queryClass = $query::class;

        if (!isset($this->handlers[$queryClass])) {
            throw new RuntimeException("Kein Handler für Query \"{$queryClass}\" registriert.");
        }

        return $this->handlers[$queryClass]->handle($query);
    }
}
