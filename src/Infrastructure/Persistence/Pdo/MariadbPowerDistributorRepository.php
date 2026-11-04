<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Pdo;

use App\Domain\PowerDistributor\ConnectorType;
use App\Domain\PowerDistributor\Outlet;
use App\Domain\PowerDistributor\PowerDistributor;
use App\Domain\PowerDistributor\PowerDistributorRepositoryInterface;
use App\Domain\PowerDistributor\ProtectiveDevice;
use App\Domain\PowerDistributor\ProtectiveDeviceType;
use PDO;

final class MariadbPowerDistributorRepository implements PowerDistributorRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function findByArticle(string $articleId): ?PowerDistributor
    {
        $stmt = $this->pdo->prepare('SELECT * FROM power_distributors WHERE article_id = :id');
        $stmt->execute(['id' => $articleId]);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }

        $devices = $this->pdo->prepare('SELECT * FROM power_distributor_devices WHERE article_id = :id ORDER BY sort_order, label');
        $devices->execute(['id' => $articleId]);
        $outlets = $this->pdo->prepare('SELECT * FROM power_distributor_outlets WHERE article_id = :id ORDER BY sort_order');
        $outlets->execute(['id' => $articleId]);

        return new PowerDistributor(
            articleId: $row['article_id'],
            inletType: ConnectorType::tryFrom((string) $row['inlet_type']) ?? ConnectorType::Schuko,
            notes: $row['notes'] ?? null,
            devices: array_map(static fn (array $d) => new ProtectiveDevice(
                id: $d['id'],
                label: $d['label'],
                type: ProtectiveDeviceType::tryFrom((string) $d['device_type']) ?? ProtectiveDeviceType::Mcb,
                poles: $d['poles'] !== null ? (int) $d['poles'] : null,
                rcdType: $d['rcd_type'] ?? null,
                residualCurrentMa: $d['residual_current_ma'] !== null ? (int) $d['residual_current_ma'] : null,
                ratedCurrentA: $d['rated_current_a'] !== null ? (int) $d['rated_current_a'] : null,
                characteristic: $d['characteristic'] ?? null,
                upstreamDeviceId: $d['upstream_device_id'] ?? null,
                sortOrder: (int) $d['sort_order'],
                fuseSystem: $d['fuse_system'] ?? null,
                threeSingle: (bool) ($d['three_single'] ?? false),
                phase: $d['phase'] ?? null,
            ), $devices->fetchAll()),
            outlets: array_values(array_filter(array_map(static fn (array $o) => ConnectorType::tryFrom((string) $o['connector_type']) !== null ? new Outlet(
                id: $o['id'],
                connectorType: ConnectorType::from((string) $o['connector_type']),
                quantity: (int) $o['quantity'],
                label: $o['label'] ?? null,
                deviceId: $o['device_id'] ?? null,
                sortOrder: (int) $o['sort_order'],
                phase: $o['phase'] ?? null,
            ) : null, $outlets->fetchAll()))),
        );
    }

    public function save(PowerDistributor $distributor): void
    {
        $started = !$this->pdo->inTransaction();
        if ($started) {
            $this->pdo->beginTransaction();
        }
        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO power_distributors (article_id, inlet_type, notes) VALUES (:article_id, :inlet_type, :notes)
                 ON DUPLICATE KEY UPDATE inlet_type = VALUES(inlet_type), notes = VALUES(notes)'
            );
            $stmt->execute(['article_id' => $distributor->articleId, 'inlet_type' => $distributor->inletType->value, 'notes' => $distributor->notes]);

            $this->pdo->prepare('DELETE FROM power_distributor_outlets WHERE article_id = :id')->execute(['id' => $distributor->articleId]);
            $this->pdo->prepare('UPDATE power_distributor_devices SET upstream_device_id = NULL WHERE article_id = :id')->execute(['id' => $distributor->articleId]);
            $this->pdo->prepare('DELETE FROM power_distributor_devices WHERE article_id = :id')->execute(['id' => $distributor->articleId]);

            $insertDevice = $this->pdo->prepare(
                'INSERT INTO power_distributor_devices (id, article_id, label, device_type, poles, rcd_type, residual_current_ma, rated_current_a, characteristic, upstream_device_id, sort_order, fuse_system, three_single, phase)
                 VALUES (:id, :article_id, :label, :device_type, :poles, :rcd_type, :residual_current_ma, :rated_current_a, :characteristic, NULL, :sort_order, :fuse_system, :three_single, :phase)'
            );
            foreach ($distributor->devices as $device) {
                $insertDevice->execute([
                    'id' => $device->id,
                    'article_id' => $distributor->articleId,
                    'label' => $device->label,
                    'device_type' => $device->type->value,
                    'poles' => $device->poles,
                    'rcd_type' => $device->rcdType,
                    'residual_current_ma' => $device->residualCurrentMa,
                    'rated_current_a' => $device->ratedCurrentA,
                    'characteristic' => $device->characteristic,
                    'sort_order' => $device->sortOrder,
                    'fuse_system' => $device->fuseSystem,
                    'three_single' => $device->threeSingle ? 1 : 0,
                    'phase' => $device->phase,
                ]);
            }
            // Vorgeschaltete RCDs erst nach dem Einfügen aller Schutzorgane setzen (Fremdschlüssel).
            $setUpstream = $this->pdo->prepare('UPDATE power_distributor_devices SET upstream_device_id = :upstream WHERE id = :id');
            foreach ($distributor->devices as $device) {
                if ($device->upstreamDeviceId !== null) {
                    $setUpstream->execute(['upstream' => $device->upstreamDeviceId, 'id' => $device->id]);
                }
            }

            $insertOutlet = $this->pdo->prepare(
                'INSERT INTO power_distributor_outlets (id, article_id, connector_type, quantity, label, device_id, sort_order, phase)
                 VALUES (:id, :article_id, :connector_type, :quantity, :label, :device_id, :sort_order, :phase)'
            );
            foreach ($distributor->outlets as $outlet) {
                $insertOutlet->execute([
                    'id' => $outlet->id,
                    'article_id' => $distributor->articleId,
                    'connector_type' => $outlet->connectorType->value,
                    'quantity' => $outlet->quantity,
                    'label' => $outlet->label,
                    'device_id' => $outlet->deviceId,
                    'sort_order' => $outlet->sortOrder,
                    'phase' => $outlet->phase,
                ]);
            }

            if ($started) {
                $this->pdo->commit();
            }
        } catch (\Throwable $e) {
            if ($started && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function delete(string $articleId): void
    {
        $this->pdo->prepare('DELETE FROM power_distributor_outlets WHERE article_id = :id')->execute(['id' => $articleId]);
        $this->pdo->prepare('UPDATE power_distributor_devices SET upstream_device_id = NULL WHERE article_id = :id')->execute(['id' => $articleId]);
        $this->pdo->prepare('DELETE FROM power_distributor_devices WHERE article_id = :id')->execute(['id' => $articleId]);
        $this->pdo->prepare('DELETE FROM power_distributors WHERE article_id = :id')->execute(['id' => $articleId]);
    }

    public function articleIds(): array
    {
        return array_map(static fn (array $row) => (string) $row['article_id'], $this->pdo->query('SELECT article_id FROM power_distributors')->fetchAll());
    }
}
