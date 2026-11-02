<?php

declare(strict_types=1);

namespace App\Domain\ArticleSet;

use DateTimeImmutable;

/**
 * Prüft, ob ein Set für einen Zeitraum gebucht werden darf: feste Anzahl
 * Einsatztage (Kalendertage inkl. Start und Ende, wie Offer::maxDeploymentDays())
 * und alle zeitlichen Regeln. Nutzerentscheidung: bei Verstoß blockieren.
 */
final class ArticleSetAvailability
{
    /**
     * @param ArticleSetRule[] $rules
     * @return list<string> Verstöße (leer = erlaubt)
     */
    public static function violations(ArticleSet $set, array $rules, ?DateTimeImmutable $start, ?DateTimeImmutable $end): array
    {
        if ($start === null || $end === null) {
            return ["Für das Set „{$set->name}“ muss ein Zeitraum (Start- und Enddatum) angegeben sein."];
        }

        $violations = [];
        $days = $start->setTime(0, 0)->diff($end->setTime(0, 0))->days + 1;
        if ($end < $start || $days !== $set->deploymentDays) {
            $violations[] = "Das Set „{$set->name}“ gilt für genau {$set->deploymentDays} Einsatztag(e), der Zeitraum umfasst {$days}.";
        }
        foreach ($rules as $rule) {
            if (!$rule->allows($start, $end)) {
                $violations[] = "Das Set „{$set->name}“ ist " . $rule->describe() . ' buchbar.';
            }
        }

        return $violations;
    }
}
