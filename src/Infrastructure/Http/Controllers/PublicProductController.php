<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\Article\Query\GetArticle;
use App\Application\Query\QueryBus;
use App\Domain\Exception\NotFoundException;
use App\Infrastructure\FileStorage\LocalFileStorage;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;
use App\Infrastructure\Http\View;

/**
 * Öffentlich erreichbare (nicht angemeldete!) Seite je Artikel — wird als
 * "link" an Google Merchant Center bzw. als Produkt-URL an den WhatsApp-
 * Katalog übermittelt (beide APIs verlangen eine von außen erreichbare
 * Landingpage, siehe ArticleChannelUrlBuilder). Bewusst eine eigene, sehr
 * schlanke Seite ohne die normale, angemeldete Layout-Navigation — es
 * werden nur Daten gezeigt, die ohnehin an Google/WhatsApp übermittelt
 * werden (Name, Beschreibung, Preis, Bild), keine internen Verwaltungsdaten.
 */
final class PublicProductController
{
    public function __construct(
        private readonly QueryBus $queries,
        private readonly LocalFileStorage $fileStorage,
    ) {
    }

    public function show(Request $request): void
    {
        try {
            $detail = $this->queries->ask(new GetArticle($request->param('id')));
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());

            return;
        }

        if (!$detail->article->isActive) {
            Response::notFound('Dieser Artikel ist derzeit nicht verfügbar.');

            return;
        }

        echo View::render('public/product', ['detail' => $detail]);
    }

    public function image(Request $request): void
    {
        try {
            $detail = $this->queries->ask(new GetArticle($request->param('id')));
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());

            return;
        }

        $image = $detail->article->images[0] ?? null;
        if ($image === null) {
            Response::notFound('Für diesen Artikel ist kein Bild hinterlegt.');

            return;
        }

        $absolutePath = $this->fileStorage->absolutePath('articles', $image->filePath);
        $mimeType = function_exists('mime_content_type') ? (mime_content_type($absolutePath) ?: 'application/octet-stream') : 'application/octet-stream';

        Response::file($absolutePath, basename($image->filePath), $mimeType);
    }
}
