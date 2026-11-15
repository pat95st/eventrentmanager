<?php

declare(strict_types=1);

namespace App\Domain\OnlinePayment;

/** Secret token of the public payment page of an invoice (migration 163). */
interface InvoicePaymentLinkRepositoryInterface
{
    public function findTokenForInvoice(string $invoiceId): ?string;

    public function findInvoiceIdByToken(string $token): ?string;

    public function save(string $invoiceId, string $token): void;
}
