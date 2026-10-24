<?php

declare(strict_types=1);

namespace App\Application\Command;

interface CommandHandlerInterface
{
    /**
     * @param CommandInterface $command
     * @return mixed Handler dürfen z. B. die erzeugte ID zurückgeben.
     */
    public function handle(CommandInterface $command): mixed;
}
