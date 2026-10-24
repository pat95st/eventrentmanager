<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Pdo;

use App\Domain\Settings\PriceTier;
use App\Domain\Settings\PriceTierRepositoryInterface;
use PDO;

final class MariadbPriceTierRepository implements PriceTierRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function getAll(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM price_tiers ORDER BY day_count ASC');

        return array_map(
            fn (array $row) => new PriceTier(dayCount: (int) $row['day_count'], percentage: $row['percentage']),
            $stmt->fetchAll()
        );
    }

    public function saveAll(array $tiers): void
    {
        // Ersetzt die komplette Staffelung statt nur einzelne Zeilen zu
        // aktualisieren, damit auch eine Verkürzung (weniger Staffel-Tage als
        // zuvor gespeichert) korrekt zu weniger Zeilen führt, siehe
        // PriceTierRepositoryInterface::saveAll().
        $this->pdo->beginTransaction();

        try {
            $this->pdo->exec('DELETE FROM price_tiers');

            $stmt = $this->pdo->prepare('INSERT INTO price_tiers (day_count, percentage) VALUES (:day_count, :percentage)');
            foreach ($tiers as $tier) {
                $stmt->execute([
                    'day_count' => $tier->dayCount,
                    'percentage' => $tier->percentage,
                ]);
            }

            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();

            throw $e;
        }
    }
}
