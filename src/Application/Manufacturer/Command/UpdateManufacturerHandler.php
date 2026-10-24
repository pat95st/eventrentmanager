<?php

declare(strict_types=1);

namespace App\Application\Manufacturer\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Domain\Manufacturer\ManufacturerRepositoryInterface;
use App\Shared\Prefix;
use DateTimeImmutable;

final class UpdateManufacturerHandler implements CommandHandlerInterface
{
    public function __construct(private readonly ManufacturerRepositoryInterface $manufacturers)
    {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof UpdateManufacturer);

        $manufacturer = $this->manufacturers->findById($command->id);
        if ($manufacturer === null) {
            throw new NotFoundException("Hersteller \"{$command->id}\" wurde nicht gefunden.");
        }

        $name = trim($command->name);
        $prefix = Prefix::normalize($command->prefix);
        $errors = [];

        if ($name === '') {
            $errors['name'] = 'Bitte einen Namen angeben.';
        } elseif ($this->manufacturers->existsByName($name, $command->id)) {
            $errors['name'] = 'Dieser Hersteller existiert bereits.';
        }

        if ($prefix === '') {
            $errors['prefix'] = 'Bitte ein Präfix angeben.';
        } elseif (!Prefix::isValid($prefix)) {
            $errors['prefix'] = 'Das Präfix darf nur aus Großbuchstaben und Ziffern bestehen (max. 10 Zeichen).';
        } elseif ($this->manufacturers->existsByPrefix($prefix, $command->id)) {
            $errors['prefix'] = 'Dieses Präfix wird bereits von einem anderen Hersteller verwendet.';
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $manufacturer->name = $name;
        $manufacturer->prefix = $prefix;
        $manufacturer->updatedAt = new DateTimeImmutable();

        $this->manufacturers->save($manufacturer);

        return null;
    }
}
