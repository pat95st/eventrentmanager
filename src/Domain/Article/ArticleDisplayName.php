<?php

declare(strict_types=1);

namespace App\Domain\Article;

use App\Domain\Manufacturer\Manufacturer;

/**
 * Zentrale Formatierung "Hersteller Artikelbezeichnung" (z. B. "Yamaha
 * CL5"), damit der Hersteller in Listen, Formularen/Dropdowns und auf
 * Dokumenten (PDF/CSV) unternehmensweit einheitlich VOR der
 * Artikelbezeichnung genannt wird (Nutzerwunsch).
 *
 * Bewusst NICHT für die öffentliche Produktseite (resources/views/public/
 * product.php) und die REST-API (ArticleApiController) verwendet — beide
 * geben den Hersteller weiterhin als eigenes, separates Feld aus, da sie
 * von außen konsumiert werden könnten (abgestimmte Designentscheidung).
 */
final class ArticleDisplayName
{
    public static function of(Article $article, ?Manufacturer $manufacturer): string
    {
        // $manufacturer ist nullbar, weil einzelne Lesemodelle (siehe
        // ArticleListItem) bei einem inkonsistenten Datensatz (gelöschter
        // Hersteller) bewusst null statt einer Exception liefern — die
        // Anzeige fällt dann auf die reine Artikelbezeichnung zurück.
        return $manufacturer !== null
            ? trim($manufacturer->name . ' ' . $article->name)
            : $article->name;
    }

    private function __construct()
    {
        // Reine Utility-Klasse, keine Instanzen.
    }
}
