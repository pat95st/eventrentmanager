<?php

declare(strict_types=1);

namespace App\Application\Logbook\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\Vehicle\VehicleRepositoryInterface;
use DateTimeImmutable;

final class RegenerateVehicleQrTokenHandler implements CommandHandlerInterface
{
    public function __construct(private readonly VehicleRepositoryInterface $vehicles)
    {
    }

    /** @return string the new token */
    public function handle(CommandInterface $command): string
    {
        assert($command instanceof RegenerateVehicleQrToken);
        $vehicle = $this->vehicles->findById($command->vehicleId) ?? throw new NotFoundException('Das Fahrzeug wurde nicht gefunden.');
        $vehicle->logbookQrToken = self::newToken();
        $vehicle->updatedAt = new DateTimeImmutable();
        $this->vehicles->save($vehicle);

        return $vehicle->logbookQrToken;
    }

    /** 24 URL-safe characters (144 bits). */
    public static function newToken(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(18)), '+/', '-_'), '=');
    }
}
