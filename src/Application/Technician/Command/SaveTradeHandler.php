<?php

declare(strict_types=1);

namespace App\Application\Technician\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Domain\Technician\Trade;
use App\Domain\Technician\TradeRepositoryInterface;
use App\Shared\Uuid;
use DateTimeImmutable;

final class SaveTradeHandler implements CommandHandlerInterface
{
    public function __construct(private readonly TradeRepositoryInterface $trades)
    {
    }

    public function handle(CommandInterface $command): string
    {
        assert($command instanceof SaveTrade);
        $name = trim($command->name);
        $errors = [];
        if ($name === '' || mb_strlen($name) > 100) {
            $errors['name'] = 'Bitte einen Namen mit höchstens 100 Zeichen angeben.';
        }
        $sort = filter_var(trim($command->sortOrder) === '' ? '0' : trim($command->sortOrder), FILTER_VALIDATE_INT);
        if ($sort === false) {
            $errors['sortOrder'] = 'Bitte eine ganze Zahl als Reihenfolge angeben.';
        }
        $existing = $name !== '' ? $this->trades->findByName($name) : null;
        if ($existing !== null && $existing->id !== $command->id) {
            $errors['name'] = 'Das Gewerk „' . $name . '“ gibt es bereits.';
        }
        if ($errors !== []) {
            throw new ValidationException($errors);
        }
        $now = new DateTimeImmutable();
        if ($command->id !== null) {
            $trade = $this->trades->findById($command->id) ?? throw new NotFoundException('Das Gewerk wurde nicht gefunden.');
            $trade->name = $name;
            $trade->sortOrder = (int) $sort;
            $trade->updatedAt = $now;
        } else {
            $trade = new Trade(Uuid::v4(), $name, (int) $sort, $now, $now);
        }
        $this->trades->save($trade);

        return $trade->id;
    }
}
