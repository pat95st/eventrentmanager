<?php

declare(strict_types=1);

namespace App\Application\Article\Channel;

use App\Domain\Article\Article;
use App\Domain\Article\ArticleChannelListing;
use App\Domain\Article\ChannelSyncException;
use App\Domain\Article\SalesChannel;
use App\Domain\Settings\GoogleMerchantSettingsRepositoryInterface;
use App\Infrastructure\FileStorage\LocalFileStorage;
use App\Infrastructure\Integration\Google\GoogleMerchantApiClient;

final class GoogleMerchantChannelPublisher implements ArticleChannelPublisherInterface
{
    public function __construct(
        private readonly GoogleMerchantSettingsRepositoryInterface $settingsRepository,
        private readonly LocalFileStorage $fileStorage,
        private readonly GoogleMerchantApiClient $client,
    ) {
    }

    public function channel(): SalesChannel
    {
        return SalesChannel::GoogleMerchant;
    }

    public function publish(Article $article, string $productUrl, ?string $imageUrl): ArticleChannelPublishResult
    {
        $settings = $this->settingsRepository->get();

        if ($settings->merchantId === null || $settings->merchantId === '') {
            throw new ChannelSyncException('Es ist keine Merchant-Center-Konto-ID hinterlegt (siehe Einstellungen > Integrationen).');
        }
        if ($settings->serviceAccountJsonFilename === null) {
            throw new ChannelSyncException('Es ist keine Google-Service-Account-Datei hinterlegt (siehe Einstellungen > Integrationen).');
        }
        if (!$article->isBillable || $article->dailyRentalPrice === null) {
            throw new ChannelSyncException('Der Artikel hat keinen Tagesmietpreis hinterlegt — für Google Shopping wird ein Preis benötigt.');
        }
        if ($imageUrl === null) {
            throw new ChannelSyncException('Der Artikel hat kein Bild hinterlegt — Google Shopping benötigt mindestens ein Produktbild.');
        }

        $serviceAccountJson = file_get_contents($this->fileStorage->absolutePath('integrations/google-merchant', $settings->serviceAccountJsonFilename));
        if ($serviceAccountJson === false) {
            throw new ChannelSyncException('Die hinterlegte Google-Service-Account-Datei konnte nicht gelesen werden.');
        }

        $product = [
            'offerId' => $article->id,
            'title' => $article->name,
            'description' => $article->description !== null && $article->description !== '' ? $article->description : $article->name,
            'link' => $productUrl,
            'imageLink' => $imageUrl,
            'contentLanguage' => $settings->contentLanguage,
            'targetCountry' => $settings->targetCountry,
            'channel' => 'online',
            'availability' => $article->isActive ? 'in_stock' : 'out_of_stock',
            'condition' => 'new',
            'price' => ['value' => $article->dailyRentalPrice, 'currency' => 'EUR'],
        ];

        try {
            $result = $this->client->upsertProduct($serviceAccountJson, $settings->merchantId, $product);
        } catch (\Throwable $e) {
            throw new ChannelSyncException($e->getMessage(), previous: $e);
        }

        // Google stellt keine öffentliche "Produkt ansehen"-URL pro Produkt bereit
        // (Shopping-Anzeigen erscheinen dynamisch in der Suche) — verlinkt wird
        // daher die Merchant-Center-Produktliste, gefiltert auf die offerId.
        $manageLink = 'https://merchants.google.com/mc/products?a=' . rawurlencode($settings->merchantId)
            . '&query=offerId%3D' . rawurlencode($article->id);

        return new ArticleChannelPublishResult(externalId: $result['id'], externalLink: $manageLink);
    }

    public function unpublish(ArticleChannelListing $listing): void
    {
        if ($listing->externalId === null) {
            return;
        }

        $settings = $this->settingsRepository->get();
        if ($settings->merchantId === null || $settings->serviceAccountJsonFilename === null) {
            return;
        }

        $serviceAccountJson = @file_get_contents($this->fileStorage->absolutePath('integrations/google-merchant', $settings->serviceAccountJsonFilename));
        if ($serviceAccountJson === false) {
            return;
        }

        try {
            $this->client->deleteProduct($serviceAccountJson, $settings->merchantId, $listing->externalId);
        } catch (\Throwable) {
            // Best effort — siehe Interface-Docblock.
        }
    }
}
