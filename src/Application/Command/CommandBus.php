<?php

declare(strict_types=1);

namespace App\Application\Command;

use RuntimeException;

/**
 * Sehr schlanker synchroner Command-Bus: ordnet jeder Command-Klasse genau
 * einen Handler zu (in src/bootstrap.php registriert) und ruft ihn auf.
 * Kein Queueing, keine Middleware — bewusst einfach gehalten, aber die
 * saubere Trennung Command → Handler bleibt dadurch überall erzwungen.
 */
final class CommandBus
{
    /** @var array<class-string, CommandHandlerInterface> */
    private array $handlers = [];

    public function register(string $commandClass, CommandHandlerInterface $handler): void
    {
        $this->handlers[$commandClass] = $handler;
    }

    public function dispatch(CommandInterface $command): mixed
    {
        $commandClass = $command::class;

        if (!isset($this->handlers[$commandClass])) {
            throw new RuntimeException("Kein Handler für Command \"{$commandClass}\" registriert.");
        }

        return $this->handlers[$commandClass]->handle($command);
    }
}
