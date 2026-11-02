<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Pdo;

use App\Domain\ArticleSet\ArticleSet;
use App\Domain\ArticleSet\ArticleSetItem;
use App\Domain\ArticleSet\ArticleSetRepositoryInterface;
use App\Domain\ArticleSet\ArticleSetRule;
use App\Domain\ArticleSet\ArticleSetRuleType;
use App\Domain\ArticleSet\ArticleSetUpgrade;
use App\Domain\ArticleSet\ArticleSetUpgradeItem;
use App\Shared\Uuid;
use DateTimeImmutable;
use PDO;

final class MariadbArticleSetRepository implements ArticleSetRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function save(ArticleSet $set): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO article_sets (id, name, description, price, deployment_days, is_active, created_at, updated_at)
             VALUES (:id, :name, :description, :price, :deployment_days, :is_active, :created_at, :updated_at)
             ON DUPLICATE KEY UPDATE name = VALUES(name), description = VALUES(description), price = VALUES(price),
                deployment_days = VALUES(deployment_days), is_active = VALUES(is_active), updated_at = VALUES(updated_at)'
        );
        $stmt->execute([
            'id' => $set->id,
            'name' => $set->name,
            'description' => $set->description,
            'price' => $set->price,
            'deployment_days' => $set->deploymentDays,
            'is_active' => $set->isActive ? 1 : 0,
            'created_at' => $set->createdAt->format('Y-m-d H:i:s'),
            'updated_at' => $set->updatedAt->format('Y-m-d H:i:s'),
        ]);
    }

    public function findById(string $id): ?ArticleSet
    {
        $stmt = $this->pdo->prepare('SELECT * FROM article_sets WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ? $this->hydrate($row) : null;
    }

    public function findAll(bool $onlyActive = false): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM article_sets' . ($onlyActive ? ' WHERE is_active = 1' : '') . ' ORDER BY name ASC');
        $stmt->execute();

        return array_map($this->hydrate(...), $stmt->fetchAll());
    }

    public function delete(string $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM article_sets WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    public function findItems(string $setId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM article_set_items WHERE set_id = :set_id ORDER BY sort_order ASC');
        $stmt->execute(['set_id' => $setId]);

        return array_map(static fn (array $row) => new ArticleSetItem(
            id: $row['id'],
            setId: $row['set_id'],
            articleId: $row['article_id'],
            quantity: (int) $row['quantity'],
            sortOrder: (int) $row['sort_order'],
        ), $stmt->fetchAll());
    }

    public function replaceItems(string $setId, array $items): void
    {
        $this->transactional(function () use ($setId, $items): void {
            $this->pdo->prepare('DELETE FROM article_set_items WHERE set_id = :set_id')->execute(['set_id' => $setId]);
            $insert = $this->pdo->prepare(
                'INSERT INTO article_set_items (id, set_id, article_id, quantity, sort_order) VALUES (:id, :set_id, :article_id, :quantity, :sort_order)'
            );
            foreach ($items as $item) {
                $insert->execute([
                    'id' => $item->id,
                    'set_id' => $setId,
                    'article_id' => $item->articleId,
                    'quantity' => $item->quantity,
                    'sort_order' => $item->sortOrder,
                ]);
            }
        });
    }

    public function findRules(string $setId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM article_set_rules WHERE set_id = :set_id ORDER BY sort_order ASC');
        $stmt->execute(['set_id' => $setId]);

        return array_map(static fn (array $row) => new ArticleSetRule(
            id: $row['id'],
            setId: $row['set_id'],
            type: ArticleSetRuleType::from($row['type']),
            fromMonthDay: $row['from_month_day'],
            toMonthDay: $row['to_month_day'],
            fromDate: $row['from_date'] !== null ? new DateTimeImmutable($row['from_date']) : null,
            toDate: $row['to_date'] !== null ? new DateTimeImmutable($row['to_date']) : null,
            sortOrder: (int) $row['sort_order'],
        ), $stmt->fetchAll());
    }

    public function replaceRules(string $setId, array $rules): void
    {
        $this->transactional(function () use ($setId, $rules): void {
            $this->pdo->prepare('DELETE FROM article_set_rules WHERE set_id = :set_id')->execute(['set_id' => $setId]);
            $insert = $this->pdo->prepare(
                'INSERT INTO article_set_rules (id, set_id, type, from_month_day, to_month_day, from_date, to_date, sort_order)
                 VALUES (:id, :set_id, :type, :from_month_day, :to_month_day, :from_date, :to_date, :sort_order)'
            );
            foreach ($rules as $rule) {
                $insert->execute([
                    'id' => $rule->id,
                    'set_id' => $setId,
                    'type' => $rule->type->value,
                    'from_month_day' => $rule->fromMonthDay,
                    'to_month_day' => $rule->toMonthDay,
                    'from_date' => $rule->fromDate?->format('Y-m-d'),
                    'to_date' => $rule->toDate?->format('Y-m-d'),
                    'sort_order' => $rule->sortOrder,
                ]);
            }
        });
    }

    public function findUpgrades(string $setId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM article_set_upgrades WHERE set_id = :set_id ORDER BY sort_order ASC');
        $stmt->execute(['set_id' => $setId]);
        $upgrades = array_map($this->hydrateUpgrade(...), $stmt->fetchAll());
        $this->attachUpgradeItems($upgrades);

        return $upgrades;
    }

    public function findUpgradeById(string $upgradeId): ?ArticleSetUpgrade
    {
        $stmt = $this->pdo->prepare('SELECT * FROM article_set_upgrades WHERE id = :id');
        $stmt->execute(['id' => $upgradeId]);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }
        $upgrades = [$this->hydrateUpgrade($row)];
        $this->attachUpgradeItems($upgrades);

        return $upgrades[0];
    }

    /**
     * Upgrades werden per ID fortgeschrieben (nicht gelöscht und neu
     * angelegt), weil Dokumentpositionen per set_upgrade_id auf sie
     * verweisen; nur entfernte Upgrades werden gelöscht.
     */
    public function replaceUpgrades(string $setId, array $upgrades): void
    {
        $this->transactional(function () use ($setId, $upgrades): void {
            $keepIds = array_map(static fn (ArticleSetUpgrade $u) => $u->id, $upgrades);
            $existing = $this->pdo->prepare('SELECT id FROM article_set_upgrades WHERE set_id = :set_id');
            $existing->execute(['set_id' => $setId]);
            $delete = $this->pdo->prepare('DELETE FROM article_set_upgrades WHERE id = :id');
            foreach ($existing->fetchAll(PDO::FETCH_COLUMN) as $existingId) {
                if (!in_array((string) $existingId, $keepIds, true)) {
                    $delete->execute(['id' => $existingId]);
                }
            }

            $upsert = $this->pdo->prepare(
                'INSERT INTO article_set_upgrades (id, set_id, name, price, sort_order) VALUES (:id, :set_id, :name, :price, :sort_order)
                 ON DUPLICATE KEY UPDATE name = VALUES(name), price = VALUES(price), sort_order = VALUES(sort_order)'
            );
            $deleteItems = $this->pdo->prepare('DELETE FROM article_set_upgrade_items WHERE upgrade_id = :upgrade_id');
            $insertItem = $this->pdo->prepare(
                'INSERT INTO article_set_upgrade_items (id, upgrade_id, article_id, quantity) VALUES (:id, :upgrade_id, :article_id, :quantity)'
            );
            foreach ($upgrades as $upgrade) {
                $upsert->execute([
                    'id' => $upgrade->id,
                    'set_id' => $setId,
                    'name' => $upgrade->name,
                    'price' => $upgrade->price,
                    'sort_order' => $upgrade->sortOrder,
                ]);
                $deleteItems->execute(['upgrade_id' => $upgrade->id]);
                foreach ($upgrade->items as $item) {
                    $insertItem->execute([
                        'id' => $item->id !== '' ? $item->id : Uuid::v4(),
                        'upgrade_id' => $upgrade->id,
                        'article_id' => $item->articleId,
                        'quantity' => $item->quantity,
                    ]);
                }
            }
        });
    }

    public function isArticleUsed(string $articleId): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT (SELECT COUNT(*) FROM article_set_items WHERE article_id = :a1) + (SELECT COUNT(*) FROM article_set_upgrade_items WHERE article_id = :a2)'
        );
        $stmt->execute(['a1' => $articleId, 'a2' => $articleId]);

        return (int) $stmt->fetchColumn() > 0;
    }

    /** @param ArticleSetUpgrade[] $upgrades */
    private function attachUpgradeItems(array $upgrades): void
    {
        if ($upgrades === []) {
            return;
        }
        $placeholders = [];
        $params = [];
        foreach ($upgrades as $i => $upgrade) {
            $placeholders[] = ':u' . $i;
            $params['u' . $i] = $upgrade->id;
        }
        $stmt = $this->pdo->prepare('SELECT * FROM article_set_upgrade_items WHERE upgrade_id IN (' . implode(', ', $placeholders) . ')');
        $stmt->execute($params);
        $byUpgrade = [];
        foreach ($stmt->fetchAll() as $row) {
            $byUpgrade[$row['upgrade_id']][] = new ArticleSetUpgradeItem(
                id: $row['id'],
                upgradeId: $row['upgrade_id'],
                articleId: $row['article_id'],
                quantity: (int) $row['quantity'],
            );
        }
        foreach ($upgrades as $upgrade) {
            $upgrade->items = $byUpgrade[$upgrade->id] ?? [];
        }
    }

    /** @param array<string, mixed> $row */
    private function hydrate(array $row): ArticleSet
    {
        return new ArticleSet(
            id: $row['id'],
            name: $row['name'],
            description: $row['description'],
            price: (string) $row['price'],
            deploymentDays: (int) $row['deployment_days'],
            isActive: (bool) $row['is_active'],
            createdAt: new DateTimeImmutable($row['created_at']),
            updatedAt: new DateTimeImmutable($row['updated_at']),
        );
    }

    /** @param array<string, mixed> $row */
    private function hydrateUpgrade(array $row): ArticleSetUpgrade
    {
        return new ArticleSetUpgrade(
            id: $row['id'],
            setId: $row['set_id'],
            name: $row['name'],
            price: (string) $row['price'],
            sortOrder: (int) $row['sort_order'],
        );
    }

    private function transactional(callable $work): void
    {
        $own = !$this->pdo->inTransaction();
        if ($own) {
            $this->pdo->beginTransaction();
        }
        try {
            $work();
            if ($own) {
                $this->pdo->commit();
            }
        } catch (\Throwable $e) {
            if ($own) {
                $this->pdo->rollBack();
            }

            throw $e;
        }
    }
}
