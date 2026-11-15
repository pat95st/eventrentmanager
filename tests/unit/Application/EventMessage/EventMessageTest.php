<?php

declare(strict_types=1);

namespace Tests\Unit\Application\EventMessage;

use App\Application\Command\CommandBus;
use App\Application\EventMessage\Command\AcknowledgeEventMessage;
use App\Application\EventMessage\Command\CloseEventMessage;
use App\Application\EventMessage\Command\EventMessageHandler;
use App\Application\EventMessage\Command\SendEventMessage;
use App\Application\MinuteProgram\Query\MinuteProgramDetailBuilder;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Domain\MinuteProgram\MinuteProgram;
use App\Infrastructure\Http\Controllers\RunSheetActions;
use Codeception\Test\Unit;
use DateTimeImmutable;
use Tests\Support\Fakes\InMemoryEventMessageRepository;
use Tests\Support\Fakes\InMemoryMinuteProgramItemRepository;
use Tests\Support\Fakes\InMemoryMinuteProgramRepository;
use Tests\Support\Fakes\InMemoryRentalRepository;

/**
 * Messages from the organizer to the stage display and FOH (migration 166).
 */
final class EventMessageTest extends Unit
{
    private InMemoryEventMessageRepository $messages;
    private InMemoryMinuteProgramRepository $programs;
    private CommandBus $bus;

    protected function _before(): void
    {
        $this->messages = new InMemoryEventMessageRepository();
        $this->programs = new InMemoryMinuteProgramRepository();
        foreach ([['p1', 'r1'], ['p2', 'r1'], ['p3', 'r2']] as [$id, $rental]) {
            $this->programs->save(new MinuteProgram(
                id: $id,
                rentalId: $rental,
                name: 'Programm ' . $id,
                room: null,
                startAt: new DateTimeImmutable('2026-11-15 20:00:00'),
                jingleBefore: false,
                handheldMicCount: 0,
                headsetMicCount: 0,
                currentDelaySeconds: 0,
                createdAt: new DateTimeImmutable('2026-11-01 10:00:00'),
                updatedAt: new DateTimeImmutable('2026-11-01 10:00:00'),
            ));
        }
        $this->bus = new CommandBus();
        $handler = new EventMessageHandler($this->messages, $this->programs);
        foreach ([SendEventMessage::class, AcknowledgeEventMessage::class, CloseEventMessage::class] as $command) {
            $this->bus->register($command, $handler);
        }
    }

    private function send(string $body, bool $stage, bool $foh, ?string $program = null, string $rental = 'r1'): string
    {
        return $this->bus->dispatch(new SendEventMessage($rental, $body, $stage, $foh, false, $program, 'organizer', 'login1', 'Max (Veranstalter)'));
    }

    /** @return array<string, mixed> */
    private function payload(string $programId, string $audience): array
    {
        $program = $this->programs->findById($programId);
        $this->assertNotNull($program);
        $detail = (new MinuteProgramDetailBuilder(new InMemoryMinuteProgramItemRepository()))->build($program, new DateTimeImmutable('2026-11-15 19:00:00'));

        return (new RunSheetActions($this->bus, new InMemoryRentalRepository(), null, $this->messages))->payload($detail, false, $audience);
    }

    /** @param array<string, mixed> $payload @return list<string> */
    private static function texts(array $payload): array
    {
        return array_map(static fn (array $m): string => $m['text'], $payload['messages']);
    }

    public function testSendValidatesTextAndTargets(): void
    {
        foreach ([['   ', true, true], ['Hallo', false, false], [str_repeat('x', 501), true, false]] as [$body, $stage, $foh]) {
            try {
                $this->send($body, $stage, $foh);
                $this->fail('ValidationException expected');
            } catch (ValidationException) {
                $this->addToAssertionCount(1);
            }
        }
        $this->expectException(ValidationException::class);
        $this->send('Fremdes Programm', true, true, 'p3');
    }

    public function testStageDisplayOnlyGetsStageMessagesOfItsProgram(): void
    {
        $this->send('An alle', true, true);
        $this->send("Nur FOH\r\nzweite Zeile", false, true);
        $this->send('Nur Bühne p1', true, false, 'p1');
        $this->send('Nur Bühne p2', true, false, 'p2');
        $this->send('Andere Veranstaltung', true, true, null, 'r2');

        $this->assertSame(['An alle', 'Nur Bühne p1'], self::texts($this->payload('p1', RunSheetActions::AUDIENCE_STAGE)));
        $this->assertSame(['An alle', "Nur FOH\nzweite Zeile", 'Nur Bühne p1'], self::texts($this->payload('p1', RunSheetActions::AUDIENCE_FOH)));
        $this->assertSame(['An alle', 'Nur Bühne p2'], self::texts($this->payload('p2', RunSheetActions::AUDIENCE_STAGE)));
    }

    public function testAcknowledgeKeepsMessageActiveAndCloseRemovesIt(): void
    {
        $id = $this->send('Bitte Licht dimmen', true, true);
        $before = $this->payload('p1', RunSheetActions::AUDIENCE_STAGE);

        $this->bus->dispatch(new AcknowledgeEventMessage($id, 'r1', 'Tontechnik (FOH)'));
        $acked = $this->payload('p1', RunSheetActions::AUDIENCE_STAGE);
        $this->assertSame('Tontechnik (FOH)', $acked['messages'][0]['acknowledgedBy']);
        $this->assertNotSame($before['revision'], $acked['revision']);

        $this->bus->dispatch(new CloseEventMessage($id, 'r1', 'Max (Veranstalter)'));
        $this->assertSame([], $this->payload('p1', RunSheetActions::AUDIENCE_FOH)['messages']);
        $this->assertSame('Max (Veranstalter)', $this->messages->findById($id)?->closedBy);
        $this->assertCount(1, $this->messages->findRecentByRental('r1'));
    }

    public function testMessagesOfOtherRentalsCannotBeClosed(): void
    {
        $id = $this->send('Hallo', true, true);
        $this->expectException(NotFoundException::class);
        $this->bus->dispatch(new CloseEventMessage($id, 'r2', 'Fremd'));
    }
}
