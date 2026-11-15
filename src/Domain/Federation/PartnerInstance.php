<?php

declare(strict_types=1);

namespace App\Domain\Federation;

use App\Domain\Exception\ValidationException;
use DateTimeImmutable;

/**
 * Another installation of this software we are befriended with (migration 157).
 *
 * Discounts are always stored from OUR point of view: $discountGranted is
 * what we grant the partner on our material, $discountReceived what the
 * partner grants us. Changing them needs the consent of both sides: one side
 * proposes, the other side accepts (see proposeDiscount()/acceptProposal()).
 * The default is a symmetric discount (same value in both directions).
 */
final class PartnerInstance
{
    public function __construct(
        public readonly string $id,
        public string $name,
        public string $baseUrl,
        public PartnerStatus $status,
        public ?string $outboundToken,
        public ?string $inboundTokenHash,
        public PartnerProfile $profile,
        public bool $autoAcceptRequests,
        public bool $allowTechnicianRequests,
        public float $discountGranted,
        public float $discountReceived,
        public ?float $proposedDiscountGranted,
        public ?float $proposedDiscountReceived,
        public ?DiscountProposalSource $proposalFrom,
        public ?string $customerId,
        public ?string $lastError,
        public ?DateTimeImmutable $lastContactAt,
        public ?DateTimeImmutable $confirmedAt,
        public readonly DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
    ) {
    }

    public function isActive(): bool
    {
        return $this->status === PartnerStatus::Active;
    }

    public function hasPendingProposal(): bool
    {
        return $this->proposalFrom !== null && $this->proposedDiscountGranted !== null && $this->proposedDiscountReceived !== null;
    }

    public function hasSymmetricDiscount(): bool
    {
        return Discount::equals($this->discountGranted, $this->discountReceived);
    }

    /** We propose new discounts (our point of view). */
    public function proposeDiscount(float $granted, float $received, DateTimeImmutable $now): void
    {
        $this->proposedDiscountGranted = $granted;
        $this->proposedDiscountReceived = $received;
        $this->proposalFrom = DiscountProposalSource::Us;
        $this->updatedAt = $now;
    }

    /**
     * The partner proposes new discounts. $partnerGranted/$partnerReceived
     * are from the PARTNER's point of view and therefore swapped here.
     */
    public function receiveProposal(float $partnerGranted, float $partnerReceived, DateTimeImmutable $now): void
    {
        $this->proposedDiscountGranted = $partnerReceived;
        $this->proposedDiscountReceived = $partnerGranted;
        $this->proposalFrom = DiscountProposalSource::Them;
        $this->updatedAt = $now;
    }

    /**
     * Accepts the pending proposal. With $expectedGranted/$expectedReceived
     * (our point of view) the proposal must match these values — used when
     * the partner reports that it accepted OUR proposal.
     */
    public function acceptProposal(DateTimeImmutable $now, ?float $expectedGranted = null, ?float $expectedReceived = null): void
    {
        if (!$this->hasPendingProposal()) {
            throw new ValidationException(['discount' => 'Es liegt kein offener Rabattvorschlag vor.']);
        }
        if (
            $expectedGranted !== null && $expectedReceived !== null
            && (!Discount::equals($expectedGranted, (float) $this->proposedDiscountGranted) || !Discount::equals($expectedReceived, (float) $this->proposedDiscountReceived))
        ) {
            throw new ValidationException(['discount' => 'Der bestätigte Rabatt weicht vom offenen Vorschlag ab.']);
        }
        $this->discountGranted = (float) $this->proposedDiscountGranted;
        $this->discountReceived = (float) $this->proposedDiscountReceived;
        $this->clearProposal($now);
    }

    public function clearProposal(DateTimeImmutable $now): void
    {
        $this->proposedDiscountGranted = null;
        $this->proposedDiscountReceived = null;
        $this->proposalFrom = null;
        $this->updatedAt = $now;
    }

    public function activate(DateTimeImmutable $now): void
    {
        $this->status = PartnerStatus::Active;
        $this->confirmedAt = $now;
        $this->updatedAt = $now;
    }

    public function recordContact(DateTimeImmutable $now): void
    {
        $this->lastContactAt = $now;
        $this->lastError = null;
    }

    public function recordError(string $message): void
    {
        $this->lastError = mb_substr($message, 0, 1000);
    }
}
