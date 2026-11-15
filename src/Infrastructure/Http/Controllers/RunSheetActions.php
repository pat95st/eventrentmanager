<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\Command\CommandBus;
use App\Application\MinuteProgram\Command\FinishMinuteProgram;
use App\Application\MinuteProgram\Command\MinuteProgramLiveClock;
use App\Application\MinuteProgram\Command\ResetMinuteProgramLive;
use App\Application\MinuteProgram\Command\SetMinuteProgramDelay;
use App\Application\MinuteProgram\Command\StartMinuteProgramItem;
use App\Application\MinuteProgram\Query\MinuteProgramDetail;
use App\Application\MinuteProgram\Query\RunSheetPayload;
use App\Application\EventMessage\Command\AcknowledgeEventMessage;
use App\Application\EventMessage\Command\CloseEventMessage;
use App\Domain\EventAudio\RentalAudioFileRepositoryInterface;
use App\Domain\EventMessage\EventMessage;
use App\Domain\EventMessage\EventMessageRepositoryInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Domain\Rental\RentalRepositoryInterface;
use App\Infrastructure\Http\Request;

/**
 * Live run sheet actions (migration 158) shared by the FOH page
 * (MinuteProgramFohController) and the stage display API
 * (RunSheetApiController): set/adjust the delay, start an item, finish and
 * reset. Input comes from form fields or a JSON body.
 *
 * Migration 166: the payload also carries the organizer's active messages;
 * FOH may acknowledge ("seen") or hide them.
 */
final class RunSheetActions
{
    /** URL segment => action (German segments for the FOH page, English for the API). */
    private const ACTIONS = [
        'verzug' => 'delay', 'delay' => 'delay',
        'start' => 'start',
        'ende' => 'finish', 'finish' => 'finish',
        'zuruecksetzen' => 'reset', 'reset' => 'reset',
        'nachricht-gesehen' => 'message-ack',
        'nachricht-ausblenden' => 'message-close',
    ];

    /** Payload audiences: FOH sees all messages, stage displays only those sent to the stage. */
    public const AUDIENCE_FOH = 'foh';
    public const AUDIENCE_STAGE = 'stage';

    public function __construct(
        private readonly CommandBus $commands,
        private readonly RentalRepositoryInterface $rentals,
        private readonly ?RentalAudioFileRepositoryInterface $audioFiles = null,
        private readonly ?EventMessageRepositoryInterface $messages = null,
    ) {
    }

    /** @return array<string, mixed> */
    public function payload(MinuteProgramDetail $detail, bool $canControl, string $audience = self::AUDIENCE_FOH): array
    {
        $program = $detail->program;
        $jingleTitle = null;
        if ($program->jingleBefore && $program->jingleAudioFileId !== null) {
            $jingleTitle = $this->audioFiles?->findById($program->jingleAudioFileId)?->title;
        }

        return RunSheetPayload::build($detail, [
            'eventName' => $this->rentals->findById($program->rentalId)?->eventName,
            'jingleTitle' => $jingleTitle,
            'canControl' => $canControl,
            'messages' => $this->messagesFor($program->rentalId, $program->id, $audience),
        ]);
    }

    /** @return list<EventMessage> */
    private function messagesFor(string $rentalId, string $programId, string $audience): array
    {
        $result = [];
        foreach ($this->messages?->findActiveByRental($rentalId) ?? [] as $message) {
            $forAudience = $audience === self::AUDIENCE_STAGE ? $message->toStage : ($message->toFoh || $message->toStage);
            if ($forAudience && $message->appliesTo($programId)) {
                $result[] = $message;
            }
        }

        return $result;
    }

    /**
     * Dispatches one action. Returns null on success, otherwise an error
     * message with HTTP status.
     *
     * @param array<string, mixed> $input
     *
     * @return array{0: string, 1: int}|null
     */
    public function perform(string $action, MinuteProgramDetail $detail, array $input, ?string $itemId = null, string $actorName = 'FOH'): ?array
    {
        $program = $detail->program;
        try {
            switch (self::ACTIONS[$action] ?? '') {
                case 'delay':
                    $this->commands->dispatch(new SetMinuteProgramDelay($program->id, $this->delayFrom($input, $detail)));
                    break;
                case 'start':
                    $item = null;
                    foreach ($detail->items as $candidate) {
                        if ($candidate->item->id === $itemId) {
                            $item = $candidate;
                        }
                    }
                    if ($item === null) {
                        return ['Der Programmpunkt wurde nicht gefunden.', 404];
                    }
                    $time = trim((string) ($input['started_at'] ?? ''));
                    $startedAt = $time !== '' ? MinuteProgramLiveClock::parseTime($time, $item->currentStart) : null;
                    $this->commands->dispatch(new StartMinuteProgramItem($program->id, $item->item->id, $startedAt));
                    break;
                case 'finish':
                    $this->commands->dispatch(new FinishMinuteProgram($program->id));
                    break;
                case 'reset':
                    $this->commands->dispatch(new ResetMinuteProgramLive($program->id));
                    break;
                case 'message-ack':
                    $this->commands->dispatch(new AcknowledgeEventMessage((string) $itemId, $program->rentalId, $actorName));
                    break;
                case 'message-close':
                    $this->commands->dispatch(new CloseEventMessage((string) $itemId, $program->rentalId, $actorName));
                    break;
                default:
                    return ['Unbekannte Aktion.', 400];
            }
        } catch (ValidationException $e) {
            return [implode(' ', $e->errors()), 422];
        } catch (NotFoundException $e) {
            return [$e->getMessage(), 404];
        }

        return null;
    }

    /**
     * Supported inputs (first match wins):
     *  - adopt=1: take over the projected delay (incl. running overrun)
     *  - adjust_seconds: relative change, e.g. 60 / -300
     *  - delay_seconds: absolute value in seconds
     *  - delay: absolute value as text ("5", "-2", "4:30", "1:05:00")
     *
     * @param array<string, mixed> $input
     */
    private function delayFrom(array $input, MinuteProgramDetail $detail): int
    {
        if (self::truthy($input['adopt'] ?? null)) {
            return $detail->projectedDelay();
        }
        if (isset($input['adjust_seconds']) && is_numeric($input['adjust_seconds'])) {
            return $detail->program->currentDelaySeconds + (int) $input['adjust_seconds'];
        }
        if (isset($input['delay_seconds']) && is_numeric($input['delay_seconds'])) {
            return (int) $input['delay_seconds'];
        }
        if (isset($input['delay']) && is_scalar($input['delay'])) {
            return MinuteProgramLiveClock::parseDelay((string) $input['delay']);
        }

        throw new ValidationException(['delay' => 'Bitte einen Verzug angeben.']);
    }

    private static function truthy(mixed $value): bool
    {
        return $value === true || $value === 1 || $value === '1' || $value === 'true';
    }

    /**
     * Form fields merged with a JSON request body (API clients).
     *
     * @return array<string, mixed>
     */
    public static function input(Request $request): array
    {
        $input = $request->post;
        $type = (string) ($_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '');
        if (str_contains($type, 'json')) {
            $raw = (string) file_get_contents('php://input');
            $json = $raw !== '' ? json_decode($raw, true) : null;
            if (is_array($json)) {
                $input = $json + $input;
            }
        }

        return $input;
    }
}
