<?php

declare(strict_types=1);

namespace App\Application\Manufacturer\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Exception\ValidationException;
use App\Domain\Manufacturer\Manufacturer;
use App\Domain\Manufacturer\ManufacturerRepositoryInterface;
use App\Shared\Prefix;
use App\Shared\Uuid;
use DateTimeImmutable;

final class CreateManufacturerHandler implements CommandHandlerInterface
{
    public function __construct(private readonly ManufacturerRepositoryInterface $manufacturers)
    {
    }

    public function handle(CommandInterface $command): string
    {
        assert($command instanceof CreateManufacturer);

        $name = trim($command->name);
        $prefix = Prefix::normalize($command->prefix);
        $errors = [];

        if ($name === '') {
            $errors['name'] = 'Bitte einen Namen angeben.';
        } elseif ($this->manufacturers->existsByName($name)) {
            $errors['name'] = 'Dieser Hersteller existiert bereits.';
        }

        if ($prefix === '') {
            $errors['prefix'] = 'Bitte ein Präfix angeben.';
        } elseif (!Prefix::isValid($prefix)) {
            $errors['prefix'] = 'Das Präfix darf nur aus Großbuchstaben und Ziffern bestehen (max. 10 Zeichen).';
        } elseif ($this->manufacturers->existsByPrefix($prefix)) {
            $errors['prefix'] = 'Dieses Präfix wird bereits von einem anderen Hersteller verwendet.';
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $now = new DateTimeImmutable();
        $manufacturer = new Manufacturer(id: Uuid::v4(), name: $name, prefix: $prefix, createdAt: $now, updatedAt: $now);

        $this->manufacturers->save($manufacturer);

        return $manufacturer->id;
    }
}
