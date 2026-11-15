<?php

declare(strict_types=1);

namespace App\Domain\OnlinePayment;

/** Payment service providers a customer can pay an invoice with (migration 161). */
enum OnlinePaymentProvider: string
{
    case PayPal = 'paypal';
    case SumUp = 'sumup';

    public function label(): string
    {
        return match ($this) {
            self::PayPal => 'PayPal',
            self::SumUp => 'SumUp (Karte, Apple Pay, Google Pay)',
        };
    }

    public function shortLabel(): string
    {
        return match ($this) {
            self::PayPal => 'PayPal',
            self::SumUp => 'SumUp',
        };
    }
}
