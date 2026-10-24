<?php

declare(strict_types=1);

namespace App\Application\MinuteProgram\Query;

/**
 * Baut das JSON für die Countdown-Live-Ansicht aus einem MinuteProgramDetail
 * (siehe MinuteProgramController::countdownData()/MinuteProgramShareController).
 * Bei leerem Programm zählt sie auf den (verzögerten) Programmstart selbst
 * herunter, bei keinem weiteren Punkt liefert sie finished = true.
 */
final class CountdownPayload
{
    /** @return array{programName: string, room: ?string, delayMinutes: int, nextLabel: ?string, nextAt: ?string, finished: bool} */
    public static function build(MinuteProgramDetail $detail): array
    {
        $program = $detail->program;

        $nextItemDetail = null;
        foreach ($detail->items as $itemDetail) {
            if ($itemDetail->isNext) {
                $nextItemDetail = $itemDetail;

                break;
            }
        }

        if ($nextItemDetail !== null) {
            return [
                'programName' => $program->name,
                'room' => $program->room,
                'delayMinutes' => $program->currentDelayMinutes,
                'nextLabel' => $nextItemDetail->item->name,
                'nextAt' => $nextItemDetail->currentStart->format(DATE_ATOM),
                'finished' => false,
            ];
        }

        if ($detail->items === []) {
            return [
                'programName' => $program->name,
                'room' => $program->room,
                'delayMinutes' => $program->currentDelayMinutes,
                'nextLabel' => null,
                'nextAt' => $program->delayedStartAt()->format(DATE_ATOM),
                'finished' => false,
            ];
        }

        return [
            'programName' => $program->name,
            'room' => $program->room,
            'delayMinutes' => $program->currentDelayMinutes,
            'nextLabel' => null,
            'nextAt' => null,
            'finished' => true,
        ];
    }
}
