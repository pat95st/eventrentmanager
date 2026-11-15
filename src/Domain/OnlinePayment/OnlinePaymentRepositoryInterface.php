<?php

declare(strict_types=1);

namespace App\Domain\OnlinePayment;

interface OnlinePaymentRepositoryInterface
{
    public function save(OnlinePayment $payment): void;

    public function findById(string $id): ?OnlinePayment;

    public function findByProviderReference(OnlinePaymentProvider $provider, string $reference): ?OnlinePayment;

    /** @return OnlinePayment[] newest first */
    public function findByInvoice(string $invoiceId): array;
}
