<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers\Api;

/** Small helpers shared by the offer, order confirmation and invoice API controllers. */
final class SalesDocumentSupport
{
    /**
     * Request body field => command parameter for position arrays. The
     * commands already use camelCase names whose snake_case form equals the
     * body field, so no renaming is necessary; kept for clarity/extension.
     *
     * @var array<string, string>
     */
    public const POSITION_ALIASES = [];

    /** @return array<string, mixed>|null */
    public static function customerRef(?object $customer): ?array
    {
        if ($customer === null) {
            return null;
        }

        return [
            'id' => $customer->id,
            'customer_number' => $customer->customerNumber,
            'display_name' => $customer->displayName(),
        ];
    }
}
