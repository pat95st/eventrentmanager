<?php

declare(strict_types=1);

namespace App\Application\PowerPlan\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Application\PowerPlan\PowerTargetOptions;
use App\Application\PowerPlan\RentalPowerSetupLoader;
use App\Domain\Exception\ValidationException;
use App\Domain\PowerDistributor\ProtectiveDevice;
use App\Domain\PowerPlan\RentalPowerPlan;
use App\Domain\PowerPlan\RentalPowerPlanRepositoryInterface;

final class SaveRentalPowerPlanHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly RentalPowerSetupLoader $loader,
        private readonly RentalPowerPlanRepositoryInterface $plans,
    ) {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof SaveRentalPowerPlan);
        $setup = $this->loader->load($command->rentalId);
        $targets = new PowerTargetOptions($setup);
        $errors = [];

        $factor = self::parseFactor($command->simultaneityFactor);
        if ($factor === false) {
            $errors['simultaneityFactor'] = 'Der Gleichzeitigkeitsfaktor muss zwischen 0,01 und 1 liegen.';
        }

        $assignments = [];
        foreach ($setup->nodes as $node) {
            $row = $command->rows[$node->key()] ?? null;
            if ($row === null) {
                continue;
            }
            $phase = trim((string) ($row['phase'] ?? ''));
            if ($phase !== '' && !in_array($phase, ProtectiveDevice::PHASES, true)) {
                $errors['phase'] = 'Bitte als Phase L1, L2 oder L3 wählen.';
                continue;
            }
            $unitFactor = self::parseFactor($row['factor'] ?? null);
            if ($unitFactor === false) {
                $errors['factor'] = $node->label . ': Der Gleichzeitigkeitsfaktor muss zwischen 0,01 und 1 liegen.';
                continue;
            }
            $assignment = $targets->decode($node->articleId, $node->unitNo, (string) ($row['target'] ?? ''), $phase !== '' ? $phase : null, $unitFactor);
            if ($assignment === false) {
                $errors['target'] = $node->label . ': Das gewählte Ziel gibt es nicht (mehr).';
                continue;
            }
            if ($assignment === null) {
                if ($unitFactor !== null) {
                    // Keep the unit's own factor even while it is not plugged in.
                    $assignments[$node->key()] = new \App\Domain\PowerPlan\PowerAssignment($node->articleId, $node->unitNo, null, null, null, null, null, $unitFactor);
                }
                continue;
            }
            if ($assignment->targetKey() === $node->key()) {
                $errors['target'] = $node->label . ': Ein Stromverteiler kann nicht in sich selbst stecken.';
                continue;
            }
            $assignments[$node->key()] = $assignment;
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $this->plans->save(new RentalPowerPlan($setup->rental->id, $factor ?? RentalPowerPlan::DEFAULT_FACTOR, $assignments));

        return null;
    }

    /** @return float|null|false null = empty, false = invalid */
    public static function parseFactor(?string $raw): float|null|false
    {
        $raw = trim(str_replace(',', '.', (string) $raw));
        if ($raw === '') {
            return null;
        }
        $percent = str_ends_with($raw, '%');
        if ($percent) {
            $raw = rtrim(substr($raw, 0, -1));
        }
        if (!is_numeric($raw)) {
            return false;
        }
        $value = round((float) $raw / ($percent ? 100 : 1), 2);

        return $value >= RentalPowerPlan::MIN_FACTOR && $value <= RentalPowerPlan::MAX_FACTOR ? $value : false;
    }
}
