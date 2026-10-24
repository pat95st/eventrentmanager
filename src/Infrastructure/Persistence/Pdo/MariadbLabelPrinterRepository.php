<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Pdo;

use App\Domain\Printing\LabelPrinter;
use App\Domain\Printing\LabelPrinterRepositoryInterface;
use DateTimeImmutable;
use PDO;

final class MariadbLabelPrinterRepository implements LabelPrinterRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function save(LabelPrinter $printer): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO label_printers (
                id, name, driver_type, ip_address, port, tape_width_mm, created_at
             ) VALUES (
                :id, :name, :driver_type, :ip_address, :port, :tape_width_mm, :created_at
             )
             ON DUPLICATE KEY UPDATE
                name = :name2, driver_type = :driver_type2, ip_address = :ip_address2,
                port = :port2, tape_width_mm = :tape_width_mm2'
        );

        $stmt->execute([
            'id' => $printer->id,
            'name' => $printer->name,
            'driver_type' => $printer->driverType,
            'ip_address' => $printer->ipAddress,
            'port' => $printer->port,
            'tape_width_mm' => $printer->tapeWidthMm,
            'created_at' => $printer->createdAt->format('Y-m-d H:i:s'),
            'name2' => $printer->name,
            'driver_type2' => $printer->driverType,
            'ip_address2' => $printer->ipAddress,
            'port2' => $printer->port,
            'tape_width_mm2' => $printer->tapeWidthMm,
        ]);
    }

    public function findById(string $id): ?LabelPrinter
    {
        $stmt = $this->pdo->prepare('SELECT * FROM label_printers WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ? $this->hydrate($row) : null;
    }

    public function findAll(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM label_printers ORDER BY name ASC');

        return array_map($this->hydrate(...), $stmt->fetchAll());
    }

    public function delete(string $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM label_printers WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    private function hydrate(array $row): LabelPrinter
    {
        return new LabelPrinter(
            id: $row['id'],
            name: $row['name'],
            driverType: $row['driver_type'],
            ipAddress: $row['ip_address'],
            port: (int) $row['port'],
            tapeWidthMm: (int) $row['tape_width_mm'],
            createdAt: new DateTimeImmutable($row['created_at']),
        );
    }
}
