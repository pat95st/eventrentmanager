<?php

declare(strict_types=1);

namespace Tests\Unit\Application\DigitalStagebox;

use App\Application\DigitalStagebox\Command\DeleteDigitalProtocol;
use App\Application\DigitalStagebox\Command\DigitalProtocolSettingsHandler;
use App\Application\DigitalStagebox\Command\SaveDigitalProtocol;
use App\Domain\DigitalStagebox\ArticleDigitalStagebox;
use App\Domain\Exception\ConflictException;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use Codeception\Test\Unit;
use Tests\Support\Fakes\InMemoryDigitalStageboxRepository;

final class DigitalProtocolSettingsHandlerTest extends Unit
{
    private InMemoryDigitalStageboxRepository $repository;
    private DigitalProtocolSettingsHandler $handler;

    protected function _before(): void
    {
        $this->repository = new InMemoryDigitalStageboxRepository();
        $this->handler = new DigitalProtocolSettingsHandler($this->repository);
    }

    public function testCreatesAndRenamesProtocol(): void
    {
        $id = $this->handler->handle(new SaveDigitalProtocol(null, '  Dante  ', '10'));

        $this->assertSame('Dante', $this->repository->findProtocolById($id)?->name);
        $this->assertSame(10, $this->repository->findProtocolById($id)?->sortOrder);

        $this->handler->handle(new SaveDigitalProtocol($id, 'Dante / AES67', ''));
        $this->assertSame('Dante / AES67', $this->repository->findProtocolById($id)?->name);
        $this->assertSame(10, $this->repository->findProtocolById($id)?->sortOrder, 'Leere Reihenfolge behält den bisherigen Wert.');
    }

    public function testRejectsEmptyAndDuplicateNames(): void
    {
        $this->handler->handle(new SaveDigitalProtocol(null, 'MADI'));

        $this->expectException(ValidationException::class);
        $this->handler->handle(new SaveDigitalProtocol(null, 'madi'));
    }

    public function testRejectsEmptyName(): void
    {
        $this->expectException(ValidationException::class);
        $this->handler->handle(new SaveDigitalProtocol(null, '   '));
    }

    public function testUnknownProtocolCannotBeSaved(): void
    {
        $this->expectException(NotFoundException::class);
        $this->handler->handle(new SaveDigitalProtocol('gibt-es-nicht', 'AES50'));
    }

    public function testProtocolInUseCannotBeDeleted(): void
    {
        $id = $this->handler->handle(new SaveDigitalProtocol(null, 'AES50'));
        $this->repository->saveArticleStagebox(new ArticleDigitalStagebox('article-1', $id, 16, 0, 8, 0));

        $this->expectException(ConflictException::class);
        $this->handler->handle(new DeleteDigitalProtocol($id));
    }

    public function testUnusedProtocolIsDeleted(): void
    {
        $id = $this->handler->handle(new SaveDigitalProtocol(null, 'AVB / Milan'));
        $this->handler->handle(new DeleteDigitalProtocol($id));

        $this->assertNull($this->repository->findProtocolById($id));
    }
}
