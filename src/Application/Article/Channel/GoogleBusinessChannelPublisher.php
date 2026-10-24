<?php

declare(strict_types=1);

namespace App\Application\Article\Channel;

use App\Domain\Article\Article;
use App\Domain\Article\ArticleChannelListing;
use App\Domain\Article\ChannelSyncException;
use App\Domain\Article\SalesChannel;
use App\Domain\Settings\GoogleBusinessSettings;
use App\Domain\Settings\GoogleBusinessSettingsRepositoryInterface;
use App\Infrastructure\Integration\Google\GoogleBusinessApiClient;
use DateTimeImmutable;

/**
 * Veröffentlicht einen Artikel als Beitrag im Google Unternehmensprofil
 * (siehe Docblock von GoogleBusinessSettings/GoogleBusinessApiClient dazu,
 * warum es sich um einen Beitrag statt eines "Produkts" handelt).
 */
final class GoogleBusinessChannelPublisher implements ArticleChannelPublisherInterface
{
    public function __construct(
        private readonly GoogleBusinessSettingsRepositoryInterface $settingsRepository,
        private readonly GoogleBusinessApiClient $client,
    ) {
    }

    public function channel(): SalesChannel
    {
        return SalesChannel::GoogleBusiness;
    }

    public function publish(Article $article, string $productUrl, ?string $imageUrl): ArticleChannelPublishResult
    {
        $settings = $this->settingsRepository->get();

        if ($settings->clientId === null || $settings->refreshToken === null) {
            throw new ChannelSyncException('Es ist keine Verbindung zum Google Unternehmensprofil hergestellt (siehe Einstellungen > Integrationen).');
        }
        if ($settings->locationId === null) {
            throw new ChannelSyncException('Es konnte kein Standort im Google Unternehmensprofil ermittelt werden — bitte die Verbindung neu herstellen.');
        }

        $accessToken = $this->ensureFreshAccessToken($settings);

        $summary = 'Neu im Sortiment: ' . $article->name
            . ($article->description !== null && $article->description !== '' ? "\n\n" . $article->description : '');

        try {
            $result = $this->client->createProductPost($accessToken, $settings->locationId, $summary, $productUrl, $imageUrl);
        } catch (\Throwable $e) {
            throw new ChannelSyncException($e->getMessage(), previous: $e);
        }

        // Deep-Link ins Beiträge-Verwaltungsmodul des Unternehmensprofils für den
        // konkreten Standort (Format https://business.google.com/posts/l/<Standort-ID>).
        $locationParts = explode('/locations/', $settings->locationId);
        $locationNumericId = $locationParts[1] ?? $settings->locationId;

        return new ArticleChannelPublishResult(
            externalId: $result['name'],
            externalLink: 'https://business.google.com/posts/l/' . rawurlencode($locationNumericId)
        );
    }

    public function unpublish(ArticleChannelListing $listing): void
    {
        if ($listing->externalId === null) {
            return;
        }

        $settings = $this->settingsRepository->get();
        if ($settings->clientId === null || $settings->refreshToken === null) {
            return;
        }

        try {
            $accessToken = $this->ensureFreshAccessToken($settings);
            $this->client->deletePost($accessToken, $listing->externalId);
        } catch (\Throwable) {
            // Best effort — siehe Interface-Docblock.
        }
    }

    private function ensureFreshAccessToken(GoogleBusinessSettings $settings): string
    {
        $stillValid = $settings->accessToken !== null
            && $settings->accessTokenExpiresAt !== null
            && $settings->accessTokenExpiresAt > new DateTimeImmutable('+60 seconds');

        if ($stillValid) {
            /** @var string $token */
            $token = $settings->accessToken;

            return $token;
        }

        if ($settings->clientId === null || $settings->clientSecret === null || $settings->refreshToken === null) {
            throw new ChannelSyncException('Die Verbindung zum Google Unternehmensprofil ist unvollständig — bitte neu verbinden.');
        }

        try {
            $refreshed = $this->client->refreshAccessToken($settings->clientId, $settings->clientSecret, $settings->refreshToken);
        } catch (\Throwable $e) {
            throw new ChannelSyncException('Das Zugangs-Token für das Google Unternehmensprofil konnte nicht erneuert werden: ' . $e->getMessage(), previous: $e);
        }

        $settings->accessToken = $refreshed['accessToken'];
        $settings->accessTokenExpiresAt = $refreshed['expiresAt'];
        $this->settingsRepository->save($settings);

        return $refreshed['accessToken'];
    }
}
