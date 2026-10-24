<?php

declare(strict_types=1);

namespace App\Application\Dunning\Command;

use App\Application\Command\CommandInterface;

final class CreateDunningNotice implements CommandInterface
{
    public function __construct(
        public readonly string $invoiceId,
        public readonly string $dunningLevelId,
        // 'manual' (Standard, über die Rechnungs-Detailansicht) oder
        // 'automatic' (siehe bin/send-dunning-notices.php) — rein
        // informativ, hat keinen Einfluss auf die Verarbeitung selbst.
        public readonly string $createdBy = 'manual',
    ) {
    }
}
