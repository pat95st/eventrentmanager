<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Pdo;

use App\Domain\DryHire\DryHireRepositoryInterface;
use App\Domain\DryHire\DryHireTier;
use PDO;

final class MariadbDryHireRepository implements DryHireRepositoryInterface
{
    /** @var array<string, string>|null */
    private ?array $priceCache = null;

    public function __construct(private readonly PDO $pdo)
    {
    }

    public function articlePrices(): array
    {
        if ($this->priceCache === null) {
            $this->priceCache = [];
            foreach ($this->pdo->query('SELECT article_id, daily_price FROM article_dry_hire_prices')->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $this->priceCache[(string) $row['article_id']] = (string) $row['daily_price'];
            }
        }

        return $this->priceCache;
    }

    public function articlePrice(string $articleId): ?string
    {
        return $this->articlePrices()[$articleId] ?? null;
    }

    public function setArticlePrice(string $articleId, ?string $dailyPrice): void
    {
        $this->priceCache = null;
        if ($dailyPrice === null) {
            $this->pdo->prepare('DELETE FROM article_dry_hire_prices WHERE article_id = :id')->execute(['id' => $articleId]);

            return;
        }
        $this->pdo->prepare(
            'INSERT INTO article_dry_hire_prices (article_id, daily_price, updated_at) VALUES (:id, :price, NOW())
             ON DUPLICATE KEY UPDATE daily_price = VALUES(daily_price), updated_at = NOW()'
        )->execute(['id' => $articleId, 'price' => $dailyPrice]);
    }

    public function isCustomerDryHire(string $customerId): bool
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM customer_dry_hire WHERE customer_id = :id');
        $stmt->execute(['id' => $customerId]);

        return $stmt->fetchColumn() !== false;
    }

    public function dryHireCustomerIds(): array
    {
        return array_map('strval', $this->pdo->query('SELECT customer_id FROM customer_dry_hire')->fetchAll(PDO::FETCH_COLUMN));
    }

    public function setCustomerDryHire(string $customerId, bool $dryHire): void
    {
        if ($dryHire) {
            $this->pdo->prepare('INSERT IGNORE INTO customer_dry_hire (customer_id, created_at) VALUES (:id, NOW())')->execute(['id' => $customerId]);
        } else {
            $this->pdo->prepare('DELETE FROM customer_dry_hire WHERE customer_id = :id')->execute(['id' => $customerId]);
        }
    }

    public function tiers(): array
    {
        $rows = $this->pdo->query('SELECT from_day, percentage FROM dry_hire_tiers ORDER BY from_day')->fetchAll(PDO::FETCH_ASSOC);
        $tiers = array_map(static fn (array $r) => new DryHireTier((int) $r['from_day'], (string) $r['percentage']), $rows);

        return $tiers !== [] ? $tiers : [new DryHireTier(1, '100.00')];
    }

    public function saveTiers(array $tiers): void
    {
        $this->pdo->beginTransaction();
        try {
            $this->pdo->exec('DELETE FROM dry_hire_tiers');
            $stmt = $this->pdo->prepare('INSERT INTO dry_hire_tiers (from_day, percentage) VALUES (:day, :pct)');
            foreach ($tiers as $tier) {
                $stmt->execute(['day' => $tier->fromDay, 'pct' => $tier->percentage]);
            }
            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function documentFlag(string $type, string $id): ?bool
    {
        $stmt = $this->pdo->prepare('SELECT enabled FROM document_dry_hire WHERE document_type = :t AND document_id = :id');
        $stmt->execute(['t' => $type, 'id' => $id]);
        $value = $stmt->fetchColumn();

        return $value === false ? null : (bool) $value;
    }

    public function setDocumentFlag(string $type, string $id, bool $enabled): void
    {
        $this->pdo->prepare(
            'INSERT INTO document_dry_hire (document_type, document_id, enabled) VALUES (:t, :id, :e)
             ON DUPLICATE KEY UPDATE enabled = VALUES(enabled)'
        )->execute(['t' => $type, 'id' => $id, 'e' => $enabled ? 1 : 0]);
    }
}
