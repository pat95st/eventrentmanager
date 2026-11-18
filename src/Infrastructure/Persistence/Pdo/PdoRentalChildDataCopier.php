<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Pdo;

use App\Application\RentalSeries\RentalChildDataCopierInterface;
use App\Shared\Uuid;
use PDO;

/**
 * Copies child records of rentals row by row (SELECT * → INSERT), so
 * columns added by later migrations are taken over automatically. Only
 * columns that must differ (ids, foreign keys, timestamps, live state) are
 * overridden.
 */
final class PdoRentalChildDataCopier implements RentalChildDataCopierInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function replaceMaterial(string $sourceRentalId, string $targetRentalId): void
    {
        $this->inTransaction(function () use ($sourceRentalId, $targetRentalId): void {
            foreach (['rental_set_bookings', 'rental_bundles', 'rental_items'] as $table) {
                $this->pdo->prepare("DELETE FROM {$table} WHERE rental_id = :id")->execute(['id' => $targetRentalId]);
            }
            $now = date('Y-m-d H:i:s');
            $this->copyRows('rental_items', 'rental_id', $sourceRentalId, [
                'rental_id' => $targetRentalId, 'accessory_checked' => 0, 'created_at' => $now, 'updated_at' => $now,
            ]);
            foreach ($this->copyRows('rental_bundles', 'rental_id', $sourceRentalId, ['rental_id' => $targetRentalId, 'created_at' => $now, 'updated_at' => $now]) as $old => $new) {
                $this->copyRows('rental_bundle_allocations', 'rental_bundle_id', $old, ['rental_bundle_id' => $new]);
            }
            foreach ($this->copyRows('rental_set_bookings', 'rental_id', $sourceRentalId, ['rental_id' => $targetRentalId, 'created_at' => $now]) as $old => $new) {
                $this->copyRows('rental_set_allocations', 'booking_id', $old, ['booking_id' => $new]);
            }
        });
    }

    public function replaceMinutePrograms(string $sourceRentalId, string $targetRentalId, int $dayOffset): void
    {
        $this->inTransaction(function () use ($sourceRentalId, $targetRentalId, $dayOffset): void {
            $this->pdo->prepare('DELETE FROM minute_programs WHERE rental_id = :id')->execute(['id' => $targetRentalId]);
            $now = date('Y-m-d H:i:s');
            $programs = $this->copyRows('minute_programs', 'rental_id', $sourceRentalId, [
                'rental_id' => $targetRentalId,
                'jingle_audio_file_id' => null, // audio files belong to the source rental
                'current_delay_minutes' => 0,
                'current_delay_seconds' => 0,
                'live_item_id' => null,
                'live_item_started_at' => null,
                'live_finished_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            foreach ($programs as $old => $new) {
                $this->copyRows('minute_program_items', 'minute_program_id', $old, ['minute_program_id' => $new]);
            }
        });
        $this->shiftMinutePrograms($targetRentalId, $dayOffset);
    }

    public function shiftMinutePrograms(string $rentalId, int $dayOffset): void
    {
        if ($dayOffset === 0) {
            return;
        }
        $this->pdo->prepare('UPDATE minute_programs SET start_at = DATE_ADD(start_at, INTERVAL :days DAY) WHERE rental_id = :id')
            ->execute(['days' => $dayOffset, 'id' => $rentalId]);
    }

    public function replacePowerPlan(string $sourceRentalId, string $targetRentalId, bool $keepVenueConnections): void
    {
        $this->inTransaction(function () use ($sourceRentalId, $targetRentalId, $keepVenueConnections): void {
            foreach (['rental_power_assignments', 'rental_power_plans'] as $table) {
                $this->pdo->prepare("DELETE FROM {$table} WHERE rental_id = :id")->execute(['id' => $targetRentalId]);
            }
            $this->copyRows('rental_power_plans', 'rental_id', $sourceRentalId, ['rental_id' => $targetRentalId, 'updated_at' => date('Y-m-d H:i:s')]);
            $overrides = ['rental_id' => $targetRentalId];
            if (!$keepVenueConnections) {
                $overrides['venue_power_connection_id'] = null;
            }
            $this->copyRows('rental_power_assignments', 'rental_id', $sourceRentalId, $overrides);
        });
    }

    public function replaceTrades(string $sourceRentalId, string $targetRentalId): void
    {
        $this->inTransaction(function () use ($sourceRentalId, $targetRentalId): void {
            $this->pdo->prepare('DELETE FROM rental_trades WHERE rental_id = :id')->execute(['id' => $targetRentalId]);
            $this->copyRows('rental_trades', 'rental_id', $sourceRentalId, ['rental_id' => $targetRentalId]);
        });
    }

    /**
     * Copies all rows of $table with $column = $value. Rows with an `id`
     * column get a new UUID; overrides are applied to existing columns only.
     *
     * @param array<string, mixed> $overrides
     * @return array<string, string> old id => new id
     */
    private function copyRows(string $table, string $column, string $value, array $overrides): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM {$table} WHERE {$column} = :value");
        $stmt->execute(['value' => $value]);
        $map = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if (array_key_exists('id', $row)) {
                $newId = Uuid::v4();
                $map[(string) $row['id']] = $newId;
                $row['id'] = $newId;
            }
            foreach ($overrides as $key => $override) {
                if (array_key_exists($key, $row)) {
                    $row[$key] = $override;
                }
            }
            $columns = array_keys($row);
            $sql = sprintf(
                'INSERT INTO %s (%s) VALUES (%s)',
                $table,
                implode(', ', array_map(static fn (string $c): string => '`' . str_replace('`', '', $c) . '`', $columns)),
                implode(', ', array_map(static fn (int $i): string => ':p' . $i, array_keys($columns))),
            );
            $insert = $this->pdo->prepare($sql);
            foreach (array_values($row) as $i => $cell) {
                $insert->bindValue(':p' . $i, $cell, $cell === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            }
            $insert->execute();
        }

        return $map;
    }

    private function inTransaction(callable $work): void
    {
        if ($this->pdo->inTransaction()) {
            $work();

            return;
        }
        $this->pdo->beginTransaction();
        try {
            $work();
            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
}
