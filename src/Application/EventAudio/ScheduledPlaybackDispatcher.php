<?php

declare(strict_types=1);

namespace App\Application\EventAudio;

use App\Application\EventAudio\Command\PlaybackHandler;
use App\Application\EventAudio\Command\RequestPlayback;
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

    public function dispatchDue(string $rentalId, DateTimeImmutable $now, int $expiryMinutes): int
    {
        $triggered = 0;
        $missedBefore = $now->modify('-' . max(1, $expiryMinutes) . ' minutes');
        foreach ($this->schedules->findDue($rentalId, $now) as $schedule) {
            if ($schedule->playAt < $missedBefore) {
                $this->schedules->claim($schedule->id, ScheduledPlaybackStatus::Missed, $now);
                continue;
            }
            if (!$this->schedules->claim($schedule->id, ScheduledPlaybackStatus::Triggered, $now)) {
                continue; // parallel bereits ausgelöst
            }
            try {
                $requestId = (string) $this->playback->handle(new RequestPlayback(
                    $rentalId,
                    'audio',
                    $schedule->audioFileId,
                    null,
                    null,
                    false,
                    $schedule->createdByType,
                    $schedule->createdById,
                    $schedule->createdByName . ' (geplant ' . $schedule->playAt->format('H:i') . ')',
                ));
                $this->schedules->attachRequest($schedule->id, $requestId);
                $triggered++;
            } catch (ValidationException) {
                // Datei inzwischen gelöscht o. Ä. – als verpasst kennzeichnen
                $schedule->status = ScheduledPlaybackStatus::Missed;
                $this->schedules->save($schedule);
            }
        }

        return $triggered;
    }
}
