<?php

declare(strict_types=1);

namespace App\Application\Rma\Query;

use App\Application\Query\QueryHandlerInterface;
use App\Application\Query\QueryInterface;
use App\Domain\Receipt\ReceiptRepositoryInterface;
use App\Domain\Rma\RmaDocumentRepositoryInterface;
use App\Domain\Rma\RmaPackagePhotoRepositoryInterface;
use App\Domain\Rma\RmaReceiptRepositoryInterface;
use App\Domain\Rma\RmaRepositoryInterface;

final class ListRmasForInventoryNumberHandler implements QueryHandlerInterface
{
    public function __construct(
        private readonly RmaRepositoryInterface $rmas,
        private readonly RmaDocumentRepositoryInterface $rmaDocuments,
        private readonly RmaPackagePhotoRepositoryInterface $rmaPackagePhotos,
        private readonly ReceiptRepositoryInterface $receiptRepository,
        private readonly RmaReceiptRepositoryInterface $rmaReceipts,
    ) {
    }

    /** @return RmaWithDocuments[] */
    public function handle(QueryInterface $query): array
    {
        assert($query instanceof ListRmasForInventoryNumber);

        $rmas = $this->rmas->findByInventoryNumber($query->inventoryNumberId);

        return array_map(
            fn ($rma) => new RmaWithDocuments(
                $rma,
                $this->rmaDocuments->findByRma($rma->id),
                $this->receiptRepository->findByIds($this->rmaReceipts->findReceiptIdsByRma($rma->id)),
                $this->rmaPackagePhotos->findByRma($rma->id),
            ),
            $rmas
        );
    }
}
