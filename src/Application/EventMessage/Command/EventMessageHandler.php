<?php

declare(strict_types=1);

namespace App\Application\EventMessage\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\EventMessage\EventMessage;
use App\Domain\EventMessage\EventMessageRepositoryInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Domain\MinuteProgram\MinuteProgramRepositoryInterface;
use App\Shared\Uuid;
use DateTimeImmutable;

/**
 * Messages to the stage display / FOH (migration 166): SendEventMessage,
 * AcknowledgeEventMessage, CloseEventMessage.
 */
final class EventMessageHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly EventMessageRepositoryInterface $messages,
        private readonly MinuteProgramRepositoryInterface $programs,
    ) {
    }

    public function handle(CommandInterface $command): mixed
    {
        return match (true) {
            $command instanceof SendEventMessage => $this->send($command),
            $command instanceof AcknowledgeEventMessage => $this->acknowledge($command),
            $command instanceof CloseEventMessage => $this->close($command),
            default => throw new \LogicException('Unknown command ' . $command::class),
        };
    }

    private function send(SendEventMessage $command): string
    {
        $errors = [];
        // Normalise line breaks, keep at most a few lines for the displays.
        $body = trim(preg_replace("/\n{3,}/", "\n\n", str_replace(["\r\n", "\r"], "\n", $command->body)) ?? '');
        if ($body === '') {
            $errors['body'] = 'Bitte eine Nachricht eingeben.';
        } elseif (mb_strlen($body) > EventMessage::MAX_LENGTH) {
            $errors['body'] = 'Die Nachricht darf höchstens ' . EventMessage::MAX_LENGTH . ' Zeichen lang sein.';
        }
        if (!$command->toStage && !$command->toFoh) {
            $errors['target'] = 'Bitte mindestens ein Ziel wählen (Stagedisplay oder FOH).';
        }
        $programId = $command->minuteProgramId !== null && $command->minuteProgramId !== '' ? $command->minuteProgramId : null;
        if ($programId !== null && $this->programs->findById($programId)?->rentalId !== $command->rentalId) {
            $errors['minute_program_id'] = 'Das gewählte Minutenprogramm gehört nicht zu dieser Veranstaltung.';
        }
        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $message = new EventMessage(
            id: Uuid::v4(),
            rentalId: $command->rentalId,
            minuteProgramId: $programId,
            body: $body,
            toStage: $command->toStage,
            toFoh: $command->toFoh,
            urgent: $command->urgent,
            sentByType: $command->sentByType,
            sentById: $command->sentById,
            sentByName: mb_substr($command->sentByName, 0, 160),
            createdAt: new DateTimeImmutable(),
        );
        $this->messages->save($message);

        return $message->id;
    }

    private function acknowledge(AcknowledgeEventMessage $command): null
    {
        $message = $this->find($command->messageId, $command->rentalId);
        if ($message->acknowledgedAt === null) {
            $message->acknowledgedAt = new DateTimeImmutable();
            $message->acknowledgedBy = mb_substr($command->byName, 0, 160);
            $this->messages->save($message);
        }

        return null;
    }

    private function close(CloseEventMessage $command): null
    {
        $message = $this->find($command->messageId, $command->rentalId);
        if ($message->closedAt === null) {
            $message->closedAt = new DateTimeImmutable();
            $message->closedBy = mb_substr($command->byName, 0, 160);
            $this->messages->save($message);
        }

        return null;
    }

    private function find(string $id, string $rentalId): EventMessage
    {
        $message = $this->messages->findById($id);
        if ($message === null || $message->rentalId !== $rentalId) {
            throw new NotFoundException('Die Nachricht wurde nicht gefunden.');
        }

        return $message;
    }
}
