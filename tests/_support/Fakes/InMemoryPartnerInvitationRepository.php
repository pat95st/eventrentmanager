<?php

declare(strict_types=1);

namespace Tests\Support\Fakes;

use App\Domain\Federation\PartnerInvitation;
use App\Domain\Federation\PartnerInvitationRepositoryInterface;

final class InMemoryPartnerInvitationRepository implements PartnerInvitationRepositoryInterface
{
    /** @var array<string, PartnerInvitation> */
    public array $invitations = [];

    public function save(PartnerInvitation $invitation): void
    {
        $this->invitations[$invitation->id] = clone $invitation;
    }

    public function findById(string $id): ?PartnerInvitation
    {
        return isset($this->invitations[$id]) ? clone $this->invitations[$id] : null;
    }

    public function findByCodeHash(string $hash): ?PartnerInvitation
    {
        foreach ($this->invitations as $invitation) {
            if ($invitation->codeHash === $hash) {
                return clone $invitation;
            }
        }

        return null;
    }

    public function findOpen(): array
    {
        return array_values(array_filter($this->invitations, static fn ($i) => $i->usedAt === null));
    }

    public function delete(string $id): void
    {
        unset($this->invitations[$id]);
    }
}
