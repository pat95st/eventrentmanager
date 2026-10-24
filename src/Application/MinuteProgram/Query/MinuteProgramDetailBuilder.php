<?php

declare(strict_types=1);

namespace App\Application\MinuteProgram\Query;

use App\Domain\MinuteProgram\MinuteProgram;
use App\Domain\MinuteProgram\MinuteProgramItemRepositoryInterface;
use DateTimeImmutable;

/**
 * Baut aus einem Minutenprogramm + seinen Punkten das vollständige
 * Lesemodell MinuteProgramDetail: berechnete plannedStart/currentStart je
 * Punkt (kumulative Dauer-Summe plus aktuelle Verzögerung) sowie
 * isCurrent/isNext-Marker für die Live-Ansichten.
 *
 * Bewusst eine eigenständige, plain Service-Klasse (nur Repository-
 * Interfaces injiziert, KEINE CommandBus/QueryBus-Abhängigkeit) — wird
 * sowohl vom internen Zugriff (GetMinuteProgramDetailHandler) als auch vom
 * Freigabe-Link-Zugriff (ResolveMinuteProgramShareLinkHandler) benötigt;
 * im gesamten übrigen Code bekommt kein Application-Handler jemals selbst
 * einen CommandBus/QueryBus injiziert.
 */
final class MinuteProgramDetailBuilder
{
    public function __construct(private readonly MinuteProgramItemRepositoryInterface $items)
    {
    }

    public function build(MinuteProgram $program): MinuteProgramDetail
    {
        $items = $this->items->findByMinuteProgram($program->id);
        $now = new DateTimeImmutable();

        $cumulativeMinutes = 0;
        $details = [];

        // Der "aktuelle" Punkt ist der letzte, dessen (verzögerter) Start
        // bereits vergangen ist; der "nächste" ist der erste danach. Ohne
        // Dauer-Angabe trägt ein Punkt 0 Minuten zur kumulativen Summe bei
        // (seine eigene geplante Uhrzeit bleibt dadurch korrekt, nur der
        // übernächste Punkt rutscht nicht zusätzlich weiter).
        $currentIndex = null;
        foreach ($items as $index => $item) {
            $plannedStart = $program->startAt->modify($cumulativeMinutes . ' minutes');
            $currentStart = $plannedStart->modify($program->currentDelayMinutes . ' minutes');

            if ($currentStart <= $now) {
                $currentIndex = $index;
            }

            $details[] = [$item, $plannedStart, $currentStart];

            $cumulativeMinutes += $item->durationMinutes ?? 0;
        }

        $result = [];
        foreach ($details as $index => [$item, $plannedStart, $currentStart]) {
            $result[] = new MinuteProgramItemDetail(
                item: $item,
                plannedStart: $plannedStart,
                currentStart: $currentStart,
                isCurrent: $index === $currentIndex,
                isNext: $currentIndex === null ? $index === 0 : $index === $currentIndex + 1,
            );
        }

        return new MinuteProgramDetail($program, $result);
    }
}
