<?php

declare(strict_types=1);

namespace App\Application\RevenueGoal;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Exception\ValidationException;
use App\Shared\AmountInput;
use App\Domain\RevenueGoal\RevenueGoal;
use App\Domain\RevenueGoal\RevenueGoalKind;
use App\Domain\RevenueGoal\RevenueGoalRepositoryInterface;
use App\Shared\Uuid;

/** Umsatzziele anlegen, ändern (inkl. Betrag für ein einzelnes Jahr) und löschen (Migration 146). */
final class RevenueGoalCommandHandler implements CommandHandlerInterface
{
    public function __construct(private readonly RevenueGoalRepositoryInterface $goals)
    {
    }

    public function handle(CommandInterface $command): mixed
    {
        if ($command instanceof DeleteRevenueGoal) {
            $this->goals->delete($command->id);

            return null;
        }
        assert($command instanceof SaveRevenueGoals);

        $errors = [];
        $toSave = [];
        foreach ($this->goals->findAll() as $goal) {
            $input = $command->goals[$goal->id] ?? null;
            if ($input === null) {
                continue;
            }
            $this->apply($goal, $input, $command->year, $goal->id, $errors);
            $toSave[] = $goal;
        }

        $new = $command->new;
        if (trim($new['name']) !== '' || trim($new['amount']) !== '') {
            $goal = new RevenueGoal(Uuid::v4(), '', RevenueGoalKind::Target, '0.00');
            $this->apply($goal, $new, $command->year, 'new', $errors);
            $toSave[] = $goal;
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }
        foreach ($toSave as $goal) {
            $this->goals->save($goal);
        }

        return null;
    }

    /**
     * @param array{name: string, kind: string, amount: string, year_amount: string} $input
     * @param array<string, string> $errors Schlüssel "<Ziel-ID|new>.<Feld>"
     */
    private function apply(RevenueGoal $goal, array $input, int $year, string $key, array &$errors): void
    {
        $name = trim($input['name']);
        if ($name === '' || mb_strlen($name) > 100) {
            $errors[$key . '.name'] = 'Bitte eine Bezeichnung (höchstens 100 Zeichen) angeben.';
        }
        $goal->name = $name;
        $goal->kind = RevenueGoalKind::tryFrom($input['kind']) ?? RevenueGoalKind::Target;

        try {
            $amount = AmountInput::normalize($input['amount']);
            if ($amount === null) {
                $errors[$key . '.amount'] = 'Bitte einen Betrag angeben.';
            } else {
                $goal->amount = $amount;
            }
        } catch (\InvalidArgumentException) {
            $errors[$key . '.amount'] = 'Bitte einen gültigen Betrag angeben.';
        }

        try {
            $goal->setYearAmount($year, AmountInput::normalize($input['year_amount']));
        } catch (\InvalidArgumentException) {
            $errors[$key . '.year_amount'] = 'Bitte einen gültigen Betrag angeben oder das Feld leer lassen.';
        }
    }
}
