<?php

declare(strict_types=1);

namespace App\Application\PowerPlan;

use App\Domain\PowerPlan\PowerAssignment;
use App\Domain\PowerPlan\PowerLoadCalculator;

/**
 * Encodes and describes the places a unit can be plugged into: an outlet of
 * a planned distributor unit ("outlet:<article>:<unit>:<outlet>") or a venue
 * power connection ("venue:<connection>").
 */
final class PowerTargetOptions
{
    public function __construct(private readonly RentalPowerSetup $setup)
    {
    }

    public static function encode(?PowerAssignment $assignment): string
    {
        if ($assignment === null) {
            return '';
        }
        if ($assignment->isOutlet()) {
            return 'outlet:' . $assignment->targetArticleId . ':' . $assignment->targetUnitNo . ':' . $assignment->outletId;
        }

        return $assignment->isVenue() ? 'venue:' . $assignment->venueConnectionId : '';
    }

    /**
     * Assignment for an encoded target, null for "not plugged in".
     *
     * @return PowerAssignment|null|false false if the target does not exist (any more)
     */
    public function decode(string $articleId, int $unitNo, string $value, ?string $phase, ?float $factor): PowerAssignment|null|false
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        $parts = explode(':', $value);
        if ($parts[0] === 'venue' && count($parts) === 2) {
            return $this->setup->venueConnection($parts[1]) !== null
                ? new PowerAssignment($articleId, $unitNo, null, null, null, $parts[1], $phase, $factor)
                : false;
        }
        if ($parts[0] === 'outlet' && count($parts) === 4 && ctype_digit($parts[2])) {
            $target = $this->setup->node($parts[1] . '#' . $parts[2]);
            if ($target?->distributor === null) {
                return false;
            }
            foreach ($target->distributor->outlets as $outlet) {
                if ($outlet->id === $parts[3]) {
                    return new PowerAssignment($articleId, $unitNo, $parts[1], (int) $parts[2], $parts[3], null, $phase, $factor);
                }
            }
        }

        return false;
    }

    /**
     * Select options grouped by distributor unit / venue.
     *
     * @return array<string, array<string, string>> group label => [value => label]
     */
    public function grouped(): array
    {
        $groups = [];
        foreach ($this->setup->venueConnections as $connection) {
            $groups['Veranstaltungsort']['venue:' . $connection->id] = $connection->type->value . ($connection->location !== null ? ' · ' . $connection->location : '') . ' (' . $connection->protectionLabel() . ')';
        }
        foreach ($this->setup->nodes as $node) {
            if ($node->distributor === null) {
                continue;
            }
            foreach ($node->distributor->outlets as $outlet) {
                $value = 'outlet:' . $node->articleId . ':' . $node->unitNo . ':' . $outlet->id;
                $groups[$node->label][$value] = $this->outletText($node->distributor, $outlet);
            }
        }

        return $groups;
    }

    /** Readable target of an assignment, e.g. "Verteiler 32 A #2 › Schuko (LS F2 C16) · L2". */
    public function describe(?PowerAssignment $assignment): string
    {
        if ($assignment === null) {
            return 'nicht eingesteckt';
        }
        if ($assignment->isVenue()) {
            $connection = $this->setup->venueConnection((string) $assignment->venueConnectionId);
            $text = $connection !== null
                ? 'Anschluss ' . $connection->type->value . ($connection->location !== null ? ' (' . $connection->location . ')' : '')
                : 'unbekannter Anschluss';

            return $text . ($assignment->phase !== null ? ' · ' . $assignment->phase : '');
        }
        $target = $this->setup->node((string) $assignment->targetKey());
        if ($target?->distributor === null) {
            return 'unbekannter Stromverteiler';
        }
        foreach ($target->distributor->outlets as $outlet) {
            if ($outlet->id === $assignment->outletId) {
                $phase = $target->distributor->phaseOf($outlet) === null && $assignment->phase !== null ? ' · ' . $assignment->phase : '';

                return $target->label . ' › ' . $this->outletText($target->distributor, $outlet) . $phase;
            }
        }

        return $target->label . ' › unbekannter Abgang';
    }

    private function outletText(\App\Domain\PowerDistributor\PowerDistributor $distributor, \App\Domain\PowerDistributor\Outlet $outlet): string
    {
        $device = $distributor->device($outlet->deviceId);
        $protection = $device !== null ? $device->type->shortLabel() . ' ' . $device->label . ($device->ratedCurrentA !== null ? ' ' . ($device->characteristic ?? '') . $device->ratedCurrentA : '') : 'direkt';
        $phase = $distributor->phaseOf($outlet);

        return PowerLoadCalculator::outletName($outlet) . ($outlet->quantity > 1 ? ' ×' . $outlet->quantity : '') . ' · ' . trim($protection) . ($phase !== null ? ' · ' . $phase : '');
    }
}
