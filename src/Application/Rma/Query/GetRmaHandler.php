<?php

declare(strict_types=1);

namespace App\Application\Rma\Query;

use App\Application\Query\QueryHandlerInterface;
use App\Application\Query\QueryInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\Receipt\ReceiptRepositoryInterface;
use App\Domain\Rma\RmaDocumentRepositoryInterface;
use App\Domain\Rma\RmaPackagePhotoRepositoryInterface;
use App\Domain\Rma\RmaReceiptRepositoryInterface;
use App\Domain\Rma\RmaRepositoryInterface;

final class GetRmaHandler implements QueryHandlerInterface
{
    public function __construct(
        private readonly RmaRepositoryInterface $rmas,
        private readonly RmaDocumentRepositoryInterface $rmaDocuments,
        private readonly RmaPackagePhotoRepositoryInterface $rmaPackagePhotos,
        private readonly ReceiptRepositoryInterface $receiptRepository,
        private readonly RmaReceiptRepositoryInterface $rmaReceipts,
    ) {
    }

    public function handle(QueryInterface $query): RmaWithDocuments
    {
        assert($query instanceof GetRma);

        $rma = $this->rmas->findById($query->id);
        if ($rma === null) {
            throw new NotFoundException("RMA-Vorgang \"{$query->id}\" wurde nicht gefunden.");
        }

        $receiptIds = $this->rmaReceipts->findReceiptIdsByRma($rma->id);

        return new RmaWithDocuments(
            $rma,
            $this->rmaDocuments->findByRma($rma->id),
            $this->receiptRepository->findByIds($receiptIds),
            $this->rmaPackagePhotos->findByRma($rma->id),
        );
    }
}
