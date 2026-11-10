<?php

declare(strict_types=1);

namespace App\Application\EventAudio;

use App\Application\EventAudio\Command\PlaybackHandler;
use App\Application\EventAudio\Command\RequestPlayback;
use App\Domain\EventAudio\ScheduledPlayback;
use App\Domain\EventAudio\ScheduledPlaybackRepositoryInterface;
use App\Domain\EventAudio\ScheduledPlaybackStatus;
use App\Domain\Exception\ValidationException;
use DateTimeImmutable;

/**
 * Löst fällige geplante Einspieler aus (Migration 136): Aus jedem fälligen
 * Eintrag wird ein normaler Abspielauftrag. Aufgerufen bei jedem Abruf durch
 * das Abspielgerät (alle paar Sekunden) und durch die Live-Oberfläche – ein
 * Cronjob ist daher nicht nötig. Ist die Startzeit länger als die
 * Verfallszeit her (Gerät war offline), wird der Eintrag als „verpasst“
 * markiert statt verspätet abgespielt.
 */
final class ScheduledPlaybackDispatcher
{
    public function __construct(
        private readonly ScheduledPlaybackRepositoryInterface $schedules,
        private readonly PlaybackHandler $playback,
    ) {
    }

    /**
     * Serie (Migration 139): fällige Wiederholung abspielen und play_at auf den
     * nächsten Termin schieben. Lag das Gerät länger offline, werden verpasste
     * Termine übersprungen (nicht nachgespielt, nicht mitgezählt).
     */
    private function dispatchRecurring(ScheduledPlayback $schedule, DateTimeImmutable $now, DateTimeImmutable $missedBefore): int
    {
        $playAt = $schedule->playAt;
        if ($playAt < $missedBefore) {
            // auf den ersten noch nicht verfallenen Termin vorspulen
            $candidate = $schedule->nextOccurrenceAfter($missedBefore->modify('-1 second'), $schedule->repeatCount);
            if ($candidate === null) {
                // Serie ist abgelaufen: nie gespielt → verpasst, sonst abgeschlossen
                if ($schedule->repeatCount === 0) {
                    $this->schedules->claim($schedule->id, ScheduledPlaybackStatus::Missed, $now);
                } else {
                    $this->schedules->advance($schedule->id, $playAt, null, $schedule->repeatCount, null, $now);
                }

                return 0;
            }
            if ($candidate > $now) {
                $this->schedules->advance($schedule->id, $playAt, $candidate, $schedule->repeatCount, null, $now);

                return 0;
            }
            // Termin liegt im gültigen Fenster → jetzt abspielen
            if (!$this->schedules->advance($schedule->id, $playAt, $candidate, $schedule->repeatCount, null, $now)) {
                return 0;
            }
            $schedule->playAt = $candidate;
            $playAt = $candidate;
        }

        $count = $schedule->repeatCount + 1;
        $next = $schedule->nextOccurrenceAfter($now, $count);
        if (!$this->schedules->advance($schedule->id, $playAt, $next, $count, null, $now)) {
            return 0; // parallel bereits weitergeschaltet
        }
        $requestId = $this->createRequest($schedule, $playAt);
        if ($requestId === null) {
            $schedule->status = ScheduledPlaybackStatus::Missed;
            $schedule->playAt = $next ?? $playAt;
            $schedule->repeatCount = $count - 1;
            $this->schedules->save($schedule);

            return 0;
        }
        $this->schedules->attachRequest($schedule->id, $requestId);

        return 1;
    }

    private function createRequest(ScheduledPlayback $schedule, DateTimeImmutable $playAt): ?string
    {
        try {
            return (string) $this->playback->handle(new RequestPlayback(
                $schedule->rentalId,
                'audio',
                $schedule->audioFileId,
                null,
                null,
                false,
                $schedule->createdByType,
                $schedule->createdById,
                $schedule->createdByName . ' (' . ($schedule->isRecurring() ? 'Zyklus ' : 'geplant ') . $playAt->format('H:i') . ')',
            ));
        } catch (ValidationException) {
            return null; // Datei inzwischen gelöscht o. Ä.
        }
    }

    public function dispatchDue(string $rentalId, DateTimeImmutable $now, int $expiryMinutes): int
    {
        $triggered = 0;
        $missedBefore = $now->modify('-' . max(1, $expiryMinutes) . ' minutes');
        foreach ($this->schedules->findDue($rentalId, $now) as $schedule) {
            if ($schedule->isRecurring()) {
                $triggered += $this->dispatchRecurring($schedule, $now, $missedBefore);
                continue;
            }
            if ($schedule->playAt < $missedBefore) {
                $this->schedules->claim($schedule->id, ScheduledPlaybackStatus::Missed, $now);
                continue;
            }
            if (!$this->schedules->claim($schedule->id, ScheduledPlaybackStatus::Triggered, $now)) {
                continue; // parallel bereits ausgelöst
            }
            $requestId = $this->createRequest($schedule, $schedule->playAt);
            if ($requestId === null) {
                $schedule->status = ScheduledPlaybackStatus::Missed;
                $this->schedules->save($schedule);
                continue;
            }
            $this->schedules->attachRequest($schedule->id, $requestId);
            $triggered++;
        }

        return $triggered;
    }
}
