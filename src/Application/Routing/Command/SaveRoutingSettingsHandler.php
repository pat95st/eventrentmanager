<?php

declare(strict_types=1);

namespace App\Application\Routing\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Exception\ValidationException;
use App\Domain\Routing\RoutingSettingsRepositoryInterface;

/**
 * Saves the route planning settings (Settings > Routenplanung). Kept separate
 * from RoutingCommandHandler so that the web installer can use it without the
 * event/vehicle repositories the other routing commands need.
 */
final class SaveRoutingSettingsHandler implements CommandHandlerInterface
{
    public function __construct(private readonly RoutingSettingsRepositoryInterface $settings)
    {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof SaveRoutingSettings);

        $buffer = filter_var(trim($command->bufferMinutes), FILTER_VALIDATE_INT);
        if ($buffer === false || $buffer < 0 || $buffer > 600) {
            throw new ValidationException(['bufferMinutes' => 'Bitte einen Puffer zwischen 0 und 600 Minuten angeben.']);
        }
        $settings = $this->settings->get();
        $key = trim($command->apiKey);
        if ($command->removeKey) {
            $settings->apiKey = null;
        } elseif ($key !== '') {
            if (mb_strlen($key) > 255) {
                throw new ValidationException(['apiKey' => 'Der API-Schlüssel ist zu lang.']);
            }
            $settings->apiKey = $key;
        }
        $settings->bufferMinutes = $buffer;
        $this->settings->save($settings);

        return null;
    }
}
