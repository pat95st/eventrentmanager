<?php

declare(strict_types=1);

namespace App\Domain\Article;

/**
 * Externe Vertriebskanäle, an die ein Artikel optional übermittelt werden
 * kann (siehe ArticleChannelListing). Der String-Wert wird sowohl in der
 * DB (article_channel_listings.channel) als auch in URLs (z. B.
 * /artikel/{id}/kanaele/{channel}/aktivieren) verwendet.
 */
enum SalesChannel: string
{
    case GoogleMerchant = 'google_merchant';
    case GoogleBusiness = 'google_business';
    case WhatsappBusiness = 'whatsapp_business';

    public function label(): string
    {
        return match ($this) {
            self::GoogleMerchant => 'Google Shopping (Merchant Center)',
            self::GoogleBusiness => 'Google Unternehmensprofil',
            self::WhatsappBusiness => 'WhatsApp Business',
        };
    }
}
