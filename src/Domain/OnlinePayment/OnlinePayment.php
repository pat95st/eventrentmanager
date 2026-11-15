<?php

declare(strict_types=1);

namespace App\Domain\OnlinePayment;

use DateTimeImmutable;

/**
 * One payment attempt of an invoice at a payment provider (migration 161).
 * Created as 'pending' before the customer is redirected to PayPal/SumUp;
 * $providerReference is set as soon as the provider created the order or
 * checkout. Only a 'completed' payment marks the invoice as paid. The amount
 * is stored as a decimal string ("123.45"), like all other money values.
 */
final class OnlinePayment
{
    public function __construct(
        public readonly string $id,
        public readonly string $invoiceId,
        public readonly OnlinePaymentProvider $provider,
        public ?string $providerReference,
        public readonly string $amount,
        public readonly string $currency,
        public OnlinePaymentStatus $status,
        public ?string $failureReason,
        public readonly DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
        public ?DateTimeImmutable $completedAt = null,
    ) {
    }

    public function markCompleted(DateTimeImmutable $at): void
    {
        $this->status = OnlinePaymentStatus::Completed;
        $this->failureReason = null;
        $this->completedAt = $at;
        $this->updatedAt = $at;
    }

    public function markFailed(OnlinePaymentStatus $status, ?string $reason, DateTimeImmutable $at): void
    {
        $this->status = $status;
        $this->failureReason = $reason !== null ? mb_substr($reason, 0, 500) : null;
        $this->updatedAt = $at;
    }
}
