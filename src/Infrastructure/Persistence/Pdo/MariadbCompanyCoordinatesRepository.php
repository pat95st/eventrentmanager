<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Pdo;

use App\Domain\Routing\CompanyCoordinatesRepositoryInterface;
use PDO;

final class MariadbCompanyCoordinatesRepository implements CompanyCoordinatesRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function find(): ?array
    {
        $row = $this->pdo->query('SELECT latitude, longitude FROM company_settings WHERE id = 1')->fetch(PDO::FETCH_ASSOC);

        return $row && $row['latitude'] !== null && $row['longitude'] !== null ? [(float) $row['latitude'], (float) $row['longitude']] : null;
    }

    public function store(float $latitude, float $longitude): void
    {
        $this->pdo->prepare('UPDATE company_settings SET latitude = :lat, longitude = :lng WHERE id = 1')->execute(['lat' => $latitude, 'lng' => $longitude]);
    }
}
