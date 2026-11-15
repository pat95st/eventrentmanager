<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Pdo;

use App\Domain\OnlinePayment\OnlinePayment;
use App\Domain\OnlinePayment\OnlinePaymentProvider;
use App\Domain\OnlinePayment\OnlinePaymentRepositoryInterface;
use App\Domain\OnlinePayment\OnlinePaymentStatus;
use DateTimeImmutable;
use PDO;

final class MariadbOnlinePaymentRepository implements OnlinePaymentRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function save(OnlinePayment $payment): void
    {
        $this->pdo->prepare(
            'INSERT INTO invoice_online_payments
                (id, invoice_id, provider, provider_reference, amount, currency, status, failure_reason, created_at, updated_at, completed_at)
             VALUES
                (:id, :invoice_id, :provider, :provider_reference, :amount, :currency, :status, :failure_reason, :created_at, :updated_at, :completed_at)
             ON DUPLICATE KEY UPDATE
                provider_reference = VALUES(provider_reference),
                status = VALUES(status),
                failure_reason = VALUES(failure_reason),
                updated_at = VALUES(updated_at),
                completed_at = VALUES(completed_at)'
        )->execute([
            'id' => $payment->id,
            'invoice_id' => $payment->invoiceId,
            'provider' => $payment->provider->value,
            'provider_reference' => $payment->providerReference,
            'amount' => $payment->amount,
            'currency' => $payment->currency,
            'status' => $payment->status->value,
            'failure_reason' => $payment->failureReason,
            'created_at' => $payment->createdAt->format('Y-m-d H:i:s'),
            'updated_at' => $payment->updatedAt->format('Y-m-d H:i:s'),
            'completed_at' => $payment->completedAt?->format('Y-m-d H:i:s'),
        ]);
    }

    public function findById(string $id): ?OnlinePayment
    {
        $stmt = $this->pdo->prepare('SELECT * FROM invoice_online_payments WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row === false ? null : $this->hydrate($row);
    }

    public function findByProviderReference(OnlinePaymentProvider $provider, string $reference): ?OnlinePayment
    {
        $stmt = $this->pdo->prepare('SELECT * FROM invoice_online_payments WHERE provider = :provider AND provider_reference = :reference');
        $stmt->execute(['provider' => $provider->value, 'reference' => $reference]);
        $row = $stmt->fetch();

        return $row === false ? null : $this->hydrate($row);
    }

    public function findByInvoice(string $invoiceId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM invoice_online_payments WHERE invoice_id = :invoice_id ORDER BY created_at DESC, id DESC');
        $stmt->execute(['invoice_id' => $invoiceId]);

        return array_map(fn (array $row): OnlinePayment => $this->hydrate($row), $stmt->fetchAll());
    }

    /** @param array<string, mixed> $row */
    private function hydrate(array $row): OnlinePayment
    {
        return new OnlinePayment(
            id: $row['id'],
            invoiceId: $row['invoice_id'],
            provider: OnlinePaymentProvider::from($row['provider']),
            providerReference: $row['provider_reference'],
            amount: number_format((float) $row['amount'], 2, '.', ''),
            currency: $row['currency'],
            status: OnlinePaymentStatus::from($row['status']),
            failureReason: $row['failure_reason'],
            createdAt: new DateTimeImmutable($row['created_at']),
            updatedAt: new DateTimeImmutable($row['updated_at']),
            completedAt: $row['completed_at'] !== null ? new DateTimeImmutable($row['completed_at']) : null,
        );
    }
}
