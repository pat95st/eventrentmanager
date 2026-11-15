<?php

declare(strict_types=1);

namespace Tests\Support\Fakes;

use App\Domain\OnlinePayment\InvoicePaymentLinkRepositoryInterface;

final class InMemoryInvoicePaymentLinkRepository implements InvoicePaymentLinkRepositoryInterface
{
    /** @var array<string, string> invoiceId => token */
    public array $tokens = [];

    public function findTokenForInvoice(string $invoiceId): ?string
    {
        return $this->tokens[$invoiceId] ?? null;
    }

    public function findInvoiceIdByToken(string $token): ?string
    {
        $invoiceId = array_search($token, $this->tokens, true);

        return $invoiceId === false ? null : (string) $invoiceId;
    }

    public function save(string $invoiceId, string $token): void
    {
        $this->tokens[$invoiceId] = $token;
    }
}
