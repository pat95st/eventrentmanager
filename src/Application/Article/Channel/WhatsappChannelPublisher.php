<?php

declare(strict_types=1);

namespace App\Application\Article\Channel;

use App\Domain\Article\Article;
use App\Domain\Article\ArticleChannelListing;
use App\Domain\Article\ChannelSyncException;
use App\Domain\Article\SalesChannel;
use App\Domain\Settings\WhatsappBusinessSettingsRepositoryInterface;
use App\Infrastructure\Integration\Whatsapp\WhatsappCatalogApiClient;

final class WhatsappChannelPublisher implements ArticleChannelPublisherInterface
{
    public function __construct(
        private readonly WhatsappBusinessSettingsRepositoryInterface $settingsRepository,
        private readonly WhatsappCatalogApiClient $client,
    ) {
    }

    public function channel(): SalesChannel
    {
        return SalesChannel::WhatsappBusiness;
    }

    public function publish(Article $article, string $productUrl, ?string $imageUrl): ArticleChannelPublishResult
    {
        $settings = $this->settingsRepository->get();

        if ($settings->accessToken === null || $settings->accessToken === '') {
            throw new ChannelSyncException('Es ist kein Zugangs-Token für WhatsApp Business hinterlegt (siehe Einstellungen > Integrationen).');
        }
        if ($settings->catalogId === null || $settings->catalogId === '') {
            throw new ChannelSyncException('Es ist keine Katalog-ID für WhatsApp Business hinterlegt (siehe Einstellungen > Integrationen).');
        }
        if (!$article->isBillable || $article->dailyRentalPrice === null) {
            throw new ChannelSyncException('Der Artikel hat keinen Tagesmietpreis hinterlegt — für den WhatsApp-Katalog wird ein Preis benötigt.');
        }

        // Meta erwartet den Preis als Ganzzahl in der kleinsten Einheit der
        // Währung (bei EUR: Cent) — $article->dailyRentalPrice ist als
        // String mit Punkt-Dezimaltrennzeichen geführt (siehe Article-Docblock).
        $priceMinorUnits = (int) round(((float) $article->dailyRentalPrice) * 100);

        try {
            $result = $this->client->upsertProduct(
                $settings->accessToken,
                $settings->catalogId,
                $article->id,
                $article->name,
                $article->description,
                $priceMinorUnits,
                $settings->currency,
                $imageUrl,
                $productUrl,
                $article->isActive
            );
        } catch (\Throwable $e) {
            throw new ChannelSyncException($e->getMessage(), previous: $e);
        }

        $manageLink = 'https://business.facebook.com/commerce/catalogs/' . rawurlencode($settings->catalogId)
            . '/products/' . rawurlencode($result['id']);

        return new ArticleChannelPublishResult(externalId: $result['id'], externalLink: $manageLink);
    }

    public function unpublish(ArticleChannelListing $listing): void
    {
        if ($listing->externalId === null) {
            return;
        }

        $settings = $this->settingsRepository->get();
        if ($settings->accessToken === null) {
            return;
        }

        try {
            $this->client->deleteProduct($settings->accessToken, $listing->externalId);
        } catch (\Throwable) {
            // Best effort — siehe Interface-Docblock.
        }
    }
}
