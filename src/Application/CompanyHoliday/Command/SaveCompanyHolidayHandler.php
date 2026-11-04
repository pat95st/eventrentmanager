<?php

declare(strict_types=1);

namespace App\Application\CompanyHoliday\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\CompanyHoliday\CompanyHoliday;
use App\Domain\CompanyHoliday\CompanyHolidayRepositoryInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Shared\Uuid;
use DateTimeImmutable;

final class SaveCompanyHolidayHandler implements CommandHandlerInterface
{
    public function __construct(private readonly CompanyHolidayRepositoryInterface $holidays)
    {
    }

    public function handle(CommandInterface $command): string
    {
        assert($command instanceof SaveCompanyHoliday);

        $errors = [];
        $start = $this->date($command->startDate);
        $end = $this->date($command->endDate);
        if ($start === null) {
            $errors['startDate'] = 'Bitte ein gültiges Startdatum angeben.';
        }
        if ($end === null) {
            $errors['endDate'] = 'Bitte ein gültiges Enddatum angeben.';
        }
        if ($start !== null && $end !== null && $end < $start) {
            $errors['endDate'] = 'Das Enddatum kann nicht vor dem Startdatum liegen.';
        }
        $note = trim((string) $command->note);
        if (mb_strlen($note) > 255) {
            $errors['note'] = 'Die Bezeichnung darf höchstens 255 Zeichen lang sein.';
        }
        if ($errors === [] && $start !== null && $end !== null) {
            foreach ($this->holidays->findOverlapping($start, $end) as $other) {
                if ($other->id !== $command->id) {
                    $errors['startDate'] = 'Der Zeitraum überschneidet sich mit dem Betriebsurlaub ' . $other->label() . '.';
                    break;
                }
            }
        }
        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $now = new DateTimeImmutable();
        if ($command->id !== null) {
            $holiday = $this->holidays->findById($command->id);
            if ($holiday === null) {
                throw new NotFoundException('Der Betriebsurlaub wurde nicht gefunden.');
            }
            $holiday->startDate = $start;
            $holiday->endDate = $end;
            $holiday->note = $note !== '' ? $note : null;
            $holiday->updatedAt = $now;
        } else {
            $holiday = new CompanyHoliday(Uuid::v4(), $start, $end, $note !== '' ? $note : null, $now, $now);
        }
        $this->holidays->save($holiday);

        return $holiday->id;
    }

    private function date(string $value): ?DateTimeImmutable
    {
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', trim($value));

        return $parsed !== false ? $parsed : null;
    }
}
