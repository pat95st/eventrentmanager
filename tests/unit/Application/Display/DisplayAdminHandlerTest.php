<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Display;

use App\Application\Display\Command\DisplayAdminHandler;
use App\Application\Display\Command\RegenerateDisplayClientToken;
use App\Application\Display\Command\SaveDisplayClient;
use App\Application\Display\Command\SaveDisplaySettings;
use App\Domain\Display\DisplayClient;
use App\Domain\Display\DisplayRole;
use App\Domain\Exception\ValidationException;
use App\Domain\MinuteProgram\MinuteProgram;
use App\Domain\Rental\Rental;
use Codeception\Test\Unit;
use DateTimeImmutable;
use Tests\Support\Fakes\InMemoryDisplayClientRepository;
use Tests\Support\Fakes\InMemoryDisplaySettingsRepository;
use Tests\Support\Fakes\InMemoryMinuteProgramRepository;
use Tests\Support\Fakes\InMemoryRentalRepository;

final class DisplayAdminHandlerTest extends Unit
{
    private InMemoryDisplayClientRepository $clients;
    private InMemoryDisplaySettingsRepository $settings;
    private DisplayAdminHandler $handler;

    protected function _before(): void
    {
        $this->clients = new InMemoryDisplayClientRepository();
        $this->settings = new InMemoryDisplaySettingsRepository();
        $rentals = new InMemoryRentalRepository();
        $programs = new InMemoryMinuteProgramRepository();
        $at = new DateTimeImmutable('2026-11-01 10:00');
        $rentals->save(new Rental('r1', 'c1', new DateTimeImmutable('2026-11-15'), new DateTimeImmutable('2026-11-15'), 'Gala', null, null, 1, $at, $at));
        $programs->save(new MinuteProgram('p1', 'r1', 'Abend', 'Saal', new DateTimeImmutable('2026-11-15 20:00'), false, 0, 0, 0, $at, $at));
        $this->handler = new DisplayAdminHandler($this->clients, $this->settings, $rentals, $programs);
    }

    public function testCreateReturnsTokenOnceAndUsesRoleDefaults(): void
    {
        $result = $this->handler->handle(new SaveDisplayClient(null, '  FOH links ', 'foh'));
        $this->assertStringStartsWith('mvd_', (string) $result['token']);

        $client = $this->clients->findByTokenHash(DisplayClient::hashToken((string) $result['token']));
        $this->assertNotNull($client);
        $this->assertSame('FOH links', $client->name);
        $this->assertSame(DisplayRole::Foh, $client->role);
        $this->assertTrue($client->canControl());
        $this->assertNull($client->rentalId);
    }

    public function testSwitchingRoleKeepsDeviceAndToken(): void
    {
        $result = $this->handler->handle(new SaveDisplayClient(null, 'Monitor', 'foh'));
        $hash = DisplayClient::hashToken((string) $result['token']);
        $this->handler->handle(new SaveDisplayClient($result['id'], 'Monitor', 'stage', true, 'auto', null, ['allowControl' => true]));

        $client = $this->clients->findByTokenHash($hash);
        $this->assertNotNull($client);
        $this->assertSame(DisplayRole::Stage, $client->role);
        $this->assertFalse($client->canControl(), 'stage displays never control the run sheet');
    }

    public function testProgramTargetAlsoSetsItsEvent(): void
    {
        $result = $this->handler->handle(new SaveDisplayClient(null, 'Bühne', 'stage', true, 'program:p1', ' Saal '));
        $client = $this->clients->findById($result['id']);
        $this->assertNotNull($client);
        $this->assertSame('p1', $client->minuteProgramId);
        $this->assertSame('r1', $client->rentalId);
        $this->assertSame('Saal', $client->room);

        $this->handler->handle(new SaveDisplayClient($result['id'], 'Bühne', 'stage', true, 'rental:r1'));
        $client = $this->clients->findById($result['id']);
        $this->assertNotNull($client);
        $this->assertNull($client->minuteProgramId);
        $this->assertSame('r1', $client->rentalId);
    }

    public function testRejectsUnknownTargetAndRole(): void
    {
        try {
            $this->handler->handle(new SaveDisplayClient(null, 'X', 'stage', true, 'rental:nope'));
            $this->fail('expected validation error');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('target', $e->errors());
        }
        $this->expectException(ValidationException::class);
        $this->handler->handle(new SaveDisplayClient(null, 'X', 'beamer'));
    }

    public function testRegenerateInvalidatesOldToken(): void
    {
        $result = $this->handler->handle(new SaveDisplayClient(null, 'Monitor', 'stage'));
        $new = $this->handler->handle(new RegenerateDisplayClientToken($result['id']));
        $this->assertNull($this->clients->findByTokenHash(DisplayClient::hashToken((string) $result['token'])));
        $this->assertNotNull($this->clients->findByTokenHash(DisplayClient::hashToken($new)));
    }

    public function testSettingsAreClampedAndValidated(): void
    {
        $this->handler->handle(new SaveDisplaySettings(0, 90, 20));
        $settings = $this->settings->get();
        $this->assertSame(1, $settings->pollIntervalSeconds);
        $this->assertSame(90, $settings->warnSeconds);

        $this->expectException(ValidationException::class);
        $this->handler->handle(new SaveDisplaySettings(3, 30, 60));
    }
}
