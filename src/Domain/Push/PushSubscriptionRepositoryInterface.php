<?php

declare(strict_types=1);

namespace App\Domain\Push;

interface PushSubscriptionRepositoryInterface
{
    /** Erzeugt oder aktualisiert (per endpoint, siehe Migration 043 UNIQUE-Constraint) ein Push-Abo. */
    public function save(PushSubscription $subscription): void;

    public function findById(string $id): ?PushSubscription;

    public function findByEndpoint(string $endpoint): ?PushSubscription;

    /** @return PushSubscription[] Neueste zuerst. */
    public function findAllForUser(string $userId): array;

    public function delete(string $id): void;
}
