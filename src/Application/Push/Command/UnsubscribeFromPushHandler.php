<?php

declare(strict_types=1);

namespace App\Application\Push\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\Push\PushSubscriptionRepositoryInterface;

final class UnsubscribeFromPushHandler implements CommandHandlerInterface
{
    public function __construct(private readonly PushSubscriptionRepositoryInterface $subscriptions)
    {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof UnsubscribeFromPush);

        $subscription = $this->subscriptions->findById($command->subscriptionId);

        // Absichtlich derselbe generische Fehler unabhängig davon, ob die ID
        // gar nicht existiert oder einem anderen Benutzer gehört (analog zu
        // DeletePasskeyHandler) — kein Hinweis auf fremde Subscriptions.
        if ($subscription === null || $subscription->userId !== $command->requestingUserId) {
            throw new NotFoundException('Push-Abo wurde nicht gefunden.');
        }

        $this->subscriptions->delete($subscription->id);

        return null;
    }
}
