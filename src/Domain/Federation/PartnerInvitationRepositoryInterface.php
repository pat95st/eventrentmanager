<?php

declare(strict_types=1);

namespace App\Domain\Federation;

interface PartnerInvitationRepositoryInterface
{
    public function save(PartnerInvitation $invitation): void;

    public function findById(string $id): ?PartnerInvitation;

    public function findByCodeHash(string $hash): ?PartnerInvitation;

    /** @return PartnerInvitation[] unused invitations, newest first */
    public function findOpen(): array;

    public function delete(string $id): void;
}
