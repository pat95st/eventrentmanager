<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Calendar;

use App\Application\Calendar\Command\CreateCalDavCredential;
use App\Application\Calendar\Command\CreateCalDavCredentialHandler;
use App\Application\Calendar\Command\CreatedCalDavCredential;
use App\Domain\Exception\ValidationException;
use Codeception\Test\Unit;
use Tests\Support\Fakes\InMemoryCalDavCredentialRepository;

final class CreateCalDavCredentialHandlerTest extends Unit
{
    private InMemoryCalDavCredentialRepository $repository;
    private CreateCalDavCredentialHandler $handler;

    protected function _before(): void
    {
        $this->repository = new InMemoryCalDavCredentialRepository();
        $this->handler = new CreateCalDavCredentialHandler($this->repository);
    }

    public function testCreatesACredentialAndReturnsThePlaintextPasswordExactlyOnce(): void
    {
        $result = $this->handler->handle(new CreateCalDavCredential('user-1', 'iPhone'));

        $this->assertInstanceOf(CreatedCalDavCredential::class, $result);
        $this->assertNotEmpty($result->rawPassword);

        $stored = $this->repository->findById($result->id);
        $this->assertNotNull($stored);
        $this->assertSame('user-1', $stored->userId);
        $this->assertSame('iPhone', $stored->name);

        // Nur der Hash wird gespeichert — niemals das Klartext-Passwort.
        $this->assertSame(hash('sha256', $result->rawPassword), $stored->tokenHash);
        $this->assertNotSame($result->rawPassword, $stored->tokenHash);
        $this->assertSame(substr($result->rawPassword, -4), $stored->tokenLastFour);
    }

    public function testFindByTokenHashLocatesTheCredentialByItsRawPassword(): void
    {
        $result = $this->handler->handle(new CreateCalDavCredential('user-1', 'Thunderbird'));

        $found = $this->repository->findByTokenHash(hash('sha256', $result->rawPassword));

        $this->assertNotNull($found);
        $this->assertSame($result->id, $found->id);
    }

    public function testRejectsAnEmptyName(): void
    {
        $this->expectException(ValidationException::class);

        $this->handler->handle(new CreateCalDavCredential('user-1', '  '));
    }

    public function testEachCredentialGetsAUniqueRandomPassword(): void
    {
        $first = $this->handler->handle(new CreateCalDavCredential('user-1', 'A'));
        $second = $this->handler->handle(new CreateCalDavCredential('user-1', 'B'));

        $this->assertNotSame($first->rawPassword, $second->rawPassword);
    }
}
