<?php

declare(strict_types=1);

namespace App\Application\MixingConsole\Query;

use App\Application\Query\QueryInterface;

/** Mixing consoles that list the given digital stagebox as compatible. */
final class GetCompatibleConsoles implements QueryInterface
{
    public function __construct(public readonly string $stageboxArticleId)
    {
    }
}
