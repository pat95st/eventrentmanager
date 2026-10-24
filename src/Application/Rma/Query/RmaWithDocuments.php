<?php

declare(strict_types=1);

namespace App\Application\Rma\Query;

use App\Domain\Rma\Rma;

final class RmaWithDocuments
{
    /**
     * @param \App\Domain\Rma\RmaDocument[] $documents
     * @param \App\Domain\Receipt\Receipt[] $receipts
     * @param \App\Domain\Rma\RmaPackagePhoto[] $packagePhotos
     */
    public function __construct(
        public readonly Rma $rma,
        public readonly array $documents,
        public readonly array $receipts,
        public readonly array $packagePhotos = [],
    ) {
    }
}
