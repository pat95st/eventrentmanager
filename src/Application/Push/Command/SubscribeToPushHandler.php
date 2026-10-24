<?php

declare(strict_types=1);

namespace App\Application\Push\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Exception\ValidationException;
use App\Domain\Push\PushSubscription;
use App\Domain\Push\PushSubscriptionRepositoryInterface;
use App\Shared\Uuid;
use DateTimeImmutable;

final class SubscribeToPushHandler implements CommandHandlerInterface
{
    public function __construct(private readonly PushSubscriptionRepositoryInterface $subscriptions)
    {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof SubscribeToPush);

        $endpoint = trim($command->endpoint);
        $p256dh = trim($command->p256dh);
        $auth = trim($command->auth);

        if ($endpoint === '' || $p256dh === '' || $auth === '') {
            throw new ValidationException(['push' => 'Unvollständige Push-Subscription-Daten.']);
        }

        // Erneutes Abonnieren desselben Geräts/Browsers (z. B. nach
        // Cache-Löschung, derselbe endpoint) aktualisiert den bestehenden
        // Datensatz statt einen zweiten anzulegen (siehe Migration 043,
        // UNIQUE-Constraint auf endpoint).
        $existing = $this->subscriptions->findByEndpoint($endpoint);
        $id = $existing?->id ?? Uuid::v4();

        $subscription = new PushSubscription(
            id: $id,
            userId: $command->userId,
            endpoint: $endpoint,
            p256dh: $p256dh,
            auth: $auth,
            userAgent: $command->userAgent,
            createdAt: $existing?->createdAt ?? new DateTimeImmutable(),
        );

        $this->subscriptions->save($subscription);

        return $subscription->id;
    }
}
