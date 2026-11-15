<?php

declare(strict_types=1);

namespace App\Application\Federation;

use App\Domain\Federation\PartnerRequest;
use App\Domain\Federation\PartnerRequestItem;
use App\Domain\Federation\PartnerRequestStatus;

/** JSON representation of a request's state exchanged between the installations. */
final class PartnerRequestPayload
{
    /** @return array<string, mixed> */
    public static function status(PartnerRequest $request): array
    {
        return [
            'reference' => $request->reference,
            'kind' => $request->kind->value,
            'status' => $request->status->value,
            'message' => $request->responseMessage,
            'discount_percent' => $request->discountPercent,
            'items' => array_map(static fn (PartnerRequestItem $item) => $item->toArray(), $request->items),
            'technicians' => $request->technicians,
        ];
    }

    /**
     * Applies a status payload received from the providing installation to
     * our outgoing request.
     *
     * @param array<string, mixed> $payload
     */
    public static function apply(PartnerRequest $request, array $payload, \DateTimeImmutable $now): void
    {
        $status = PartnerRequestStatus::tryFrom(is_string($payload['status'] ?? null) ? $payload['status'] : '');
        if ($status !== null) {
            if ($status !== $request->status && $status->isFinal()) {
                $request->respondedAt = $now;
            }
            $request->status = $status;
        }
        if (array_key_exists('message', $payload)) {
            $message = is_scalar($payload['message']) ? trim(mb_substr((string) $payload['message'], 0, 1000)) : '';
            $request->responseMessage = $message === '' ? null : $message;
        }
        if (array_key_exists('discount_percent', $payload)) {
            $request->discountPercent = is_numeric($payload['discount_percent']) ? max(0.0, min(100.0, (float) $payload['discount_percent'])) : null;
        }
        if (isset($payload['items']) && is_array($payload['items'])) {
            $items = [];
            foreach ($payload['items'] as $row) {
                $item = is_array($row) ? PartnerRequestItem::fromArray($row) : null;
                if ($item !== null) {
                    $items[] = $item;
                }
            }
            if ($items !== []) {
                $request->items = $items;
            }
        }
        if (isset($payload['technicians']) && is_array($payload['technicians'])) {
            $request->technicians = self::technicians($payload['technicians']);
        }
        $request->syncError = null;
        $request->updatedAt = $now;
    }

    /**
     * @param array<mixed> $rows
     * @return list<array{name: string, status: string}>
     */
    public static function technicians(array $rows): array
    {
        $technicians = [];
        foreach (array_slice($rows, 0, 50) as $row) {
            if (!is_array($row) || !is_scalar($row['name'] ?? null)) {
                continue;
            }
            $technicians[] = [
                'name' => mb_substr(trim((string) $row['name']), 0, 255),
                'status' => is_scalar($row['status'] ?? null) ? mb_substr((string) $row['status'], 0, 50) : '',
            ];
        }

        return $technicians;
    }

    /**
     * @param mixed $value
     * @return string[]
     */
    public static function stringList(mixed $value, int $limit = 20): array
    {
        if (!is_array($value)) {
            return [];
        }
        $list = [];
        foreach ($value as $entry) {
            if (is_scalar($entry) && trim((string) $entry) !== '') {
                $list[] = mb_substr(trim((string) $entry), 0, 100);
            }
        }

        return array_slice(array_values(array_unique($list)), 0, $limit);
    }
}
