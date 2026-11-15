<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Pdo;

use App\Domain\OnlinePayment\InvoicePaymentLinkRepositoryInterface;
use PDO;

final class MariadbInvoicePaymentLinkRepository implements InvoicePaymentLinkRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function findTokenForInvoice(string $invoiceId): ?string
    {
        $stmt = $this->pdo->prepare('SELECT token FROM invoice_payment_links WHERE invoice_id = :invoice_id');
        $stmt->execute(['invoice_id' => $invoiceId]);
        $token = $stmt->fetchColumn();

        return $token === false ? null : (string) $token;
    }

    public function findInvoiceIdByToken(string $token): ?string
    {
        $stmt = $this->pdo->prepare('SELECT invoice_id FROM invoice_payment_links WHERE token = :token');
        $stmt->execute(['token' => $token]);
        $invoiceId = $stmt->fetchColumn();

        return $invoiceId === false ? null : (string) $invoiceId;
    }

    public function save(string $invoiceId, string $token): void
    {
        $this->pdo->prepare(
            'INSERT INTO invoice_payment_links (invoice_id, token, created_at) VALUES (:invoice_id, :token, NOW())
             ON DUPLICATE KEY UPDATE token = VALUES(token)'
        )->execute(['invoice_id' => $invoiceId, 'token' => $token]);
    }
}
