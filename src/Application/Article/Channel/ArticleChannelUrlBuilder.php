<?php

declare(strict_types=1);

namespace App\Application\Article\Channel;

use App\Domain\Article\Article;
use App\Domain\Article\ChannelSyncException;
use App\Domain\Settings\GoogleMerchantSettingsRepositoryInterface;
use App\Domain\Settings\IntegrationBaseSettingsRepositoryInterface;

/**
 * Baut die absoluten URLs, die die externen Kanal-APIs zwingend benötigen
 * (Produktlink, Bildlink) — siehe IntegrationBaseSettings-Docblock dazu,
 * warum dafür eine konfigurierte Basis-URL nötig ist.
 */
final class ArticleChannelUrlBuilder
{
    public function __construct(
        private readonly IntegrationBaseSettingsRepositoryInterface $baseSettingsRepository,
        private readonly GoogleMerchantSettingsRepositoryInterface $googleMerchantSettingsRepository,
    ) {
    }

    /** @throws ChannelSyncException wenn keine Basis-URL hinterlegt ist */
    public function productUrl(Article $article): string
    {
        $baseUrl = $this->requireBaseUrl();

        $template = $this->googleMerchantSettingsRepository->get()->productLinkTemplate;
        if ($template !== null && $template !== '') {
            return str_replace('{id}', $article->id, $template);
        }

        return $baseUrl . '/produkte/' . $article->id;
    }

    /** Öffentliche Bild-URL des ersten Artikelbilds, oder null, falls kein Bild hinterlegt ist. */
    public function imageUrl(Article $article): ?string
    {
        if ($article->images === []) {
            return null;
        }

        return $this->requireBaseUrl() . '/produkte/' . $article->id . '/bild';
    }

    /** @throws ChannelSyncException */
    private function requireBaseUrl(): string
    {
        $baseUrl = $this->baseSettingsRepository->get()->baseUrl;
        if ($baseUrl === null || $baseUrl === '') {
            throw new ChannelSyncException('Es ist keine Basis-URL dieser Installation hinterlegt (siehe Einstellungen > Integrationen) — ohne sie kann kein von außen erreichbarer Produkt-/Bildlink gebaut werden.');
        }

        return rtrim($baseUrl, '/');
    }
}
