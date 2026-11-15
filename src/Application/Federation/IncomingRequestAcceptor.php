<?php

declare(strict_types=1);

namespace App\Application\Federation;

use App\Domain\Exception\ValidationException;
use App\Domain\Federation\PartnerInstance;
use App\Domain\Federation\PartnerInstanceRepositoryInterface;
use App\Domain\Federation\PartnerRequest;
use App\Domain\Federation\PartnerRequestKind;
use App\Domain\Federation\PartnerRequestRepositoryInterface;
use App\Domain\Federation\PartnerRequestStatus;
use DateTimeImmutable;
use Throwable;

/**
 * Accepts requests a partner sent us: material requests become an event for
 * the customer representing the partner (with the requested articles),
 * technician requests an event with the chosen technicians. Used both for
 * automatic acceptance (partner setting) and for manual acceptance.
 */
final class IncomingRequestAcceptor
{
    public function __construct(
        private readonly PartnerRequestRepositoryInterface $requests,
        private readonly PartnerInstanceRepositoryInterface $partners,
        private readonly PartnerRentalGatewayInterface $gateway,
    ) {
    }

    /**
     * Articles that are not available in the requested quantity.
     *
     * @return array<string, int> article id => available quantity
     */
    public function shortages(PartnerRequest $request): array
    {
        $shortages = [];
        foreach ($request->items as $item) {
            $available = $this->gateway->availableQuantity($item->articleId, $request->startDate, $request->endDate);
            if ($available < $item->quantity) {
                $shortages[$item->articleId] = max(0, $available);
            }
        }

        return $shortages;
    }

    public function acceptMaterial(PartnerRequest $request, PartnerInstance $partner, ?string $message, DateTimeImmutable $now): void
    {
        $this->assertAcceptable($request, PartnerRequestKind::Material);

        $shortages = $this->shortages($request);
        if ($shortages !== []) {
            $lines = [];
            foreach ($request->items as $item) {
                if (isset($shortages[$item->articleId])) {
                    $lines[] = sprintf('%s: %d angefragt, %d verfügbar', $item->name !== '' ? $item->name : $item->articleId, $item->quantity, $shortages[$item->articleId]);
                }
            }
            throw new ValidationException(['items' => 'Nicht alles ist im Zeitraum verfügbar (' . implode('; ', $lines) . ').']);
        }

        $rentalId = $this->createRental($request, $partner);
        try {
            foreach ($request->items as $item) {
                $this->gateway->addRentalItem($rentalId, $item->articleId, $item->quantity);
            }
        } catch (Throwable $e) {
            $this->gateway->deleteRental($rentalId);
            throw $e;
        }

        $this->markAccepted($request, $rentalId, $message, $now);
    }

    /**
     * @param string[] $userIds
     * @param string[] $tradeIds
     */
    public function acceptTechnicians(PartnerRequest $request, PartnerInstance $partner, array $userIds, array $tradeIds, ?string $message, DateTimeImmutable $now): void
    {
        $this->assertAcceptable($request, PartnerRequestKind::Technician);
        $known = array_column($this->gateway->listTechnicians(), 'id');
        $userIds = array_values(array_intersect(array_unique($userIds), $known));
        if ($userIds === []) {
            throw new ValidationException(['technicians' => 'Bitte mindestens einen Techniker auswählen.']);
        }

        $rentalId = $this->createRental($request, $partner);
        $note = 'Anfrage von ' . $partner->name . ($request->note !== null ? ': ' . $request->note : '');
        foreach ($userIds as $userId) {
            $this->gateway->assignTechnician($rentalId, $userId, $tradeIds, mb_substr($note, 0, 500));
        }

        $this->markAccepted($request, $rentalId, $message, $now);
        $request->technicians = $this->gateway->technicianStatuses($rentalId);
        $this->requests->save($request);
    }

    private function assertAcceptable(PartnerRequest $request, PartnerRequestKind $kind): void
    {
        if (!$request->isIncoming() || $request->kind !== $kind) {
            throw new ValidationException(['request' => 'Diese Anfrage kann hier nicht zugesagt werden.']);
        }
        if ($request->status !== PartnerRequestStatus::Pending) {
            throw new ValidationException(['request' => 'Die Anfrage ist nicht mehr offen.']);
        }
    }

    private function createRental(PartnerRequest $request, PartnerInstance $partner): string
    {
        $customerId = $this->gateway->ensureCustomer($partner, $request->contactName, $request->contactEmail);
        if ($partner->customerId !== $customerId) {
            $partner->customerId = $customerId;
            $this->partners->save($partner);
        }

        return $this->gateway->createRental(
            $customerId,
            mb_substr($request->eventName . ' (' . $partner->name . ')', 0, 255),
            $request->startDate,
            $request->endDate,
            $request->eventLocation,
        );
    }

    private function markAccepted(PartnerRequest $request, string $rentalId, ?string $message, DateTimeImmutable $now): void
    {
        $request->status = PartnerRequestStatus::Accepted;
        $request->rentalId = $rentalId;
        $request->responseMessage = $message;
        $request->respondedAt = $now;
        $request->syncError = null;
        $request->updatedAt = $now;
        $this->requests->save($request);
    }
}
