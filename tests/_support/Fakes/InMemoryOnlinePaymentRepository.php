<?php

declare(strict_types=1);

namespace Tests\Support\Fakes;

use App\Domain\OnlinePayment\OnlinePayment;
use App\Domain\OnlinePayment\OnlinePaymentProvider;
use App\Domain\OnlinePayment\OnlinePaymentRepositoryInterface;

final class InMemoryOnlinePaymentRepository implements OnlinePaymentRepositoryInterface
{
    /** @var array<string, OnlinePayment> */
    public array $payments = [];

    public function save(OnlinePayment $payment): void
    {
        $this->payments[$payment->id] = clone $payment;
    }

    public function findById(string $id): ?OnlinePayment
    {
        return isset($this->payments[$id]) ? clone $this->payments[$id] : null;
    }

    public function findByProviderReference(OnlinePaymentProvider $provider, string $reference): ?OnlinePayment
    {
        foreach ($this->payments as $payment) {
            if ($payment->provider === $provider && $payment->providerReference === $reference) {
                return clone $payment;
            }
        }

        return null;
    }

    public function findByInvoice(string $invoiceId): array
    {
        $result = array_values(array_filter($this->payments, static fn (OnlinePayment $p): bool => $p->invoiceId === $invoiceId));
        usort($result, static fn (OnlinePayment $a, OnlinePayment $b): int => $b->createdAt <=> $a->createdAt);

        return array_map(static fn (OnlinePayment $p): OnlinePayment => clone $p, $result);
    }
}
