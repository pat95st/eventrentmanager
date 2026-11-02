<?php

declare(strict_types=1);

namespace App\Application\Settings\Query;

use App\Application\Query\QueryHandlerInterface;
use App\Application\Query\QueryInterface;
use App\Domain\Settings\DocumentIntroTextRepositoryInterface;

final class GetDocumentIntroTextsHandler implements QueryHandlerInterface
{
    public function __construct(private readonly DocumentIntroTextRepositoryInterface $texts)
    {
    }

    /** @return array<string, string> */
    public function handle(QueryInterface $query): array
    {
        assert($query instanceof GetDocumentIntroTexts);

        return $this->texts->getAll();
    }
}
