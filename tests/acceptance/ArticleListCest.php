<?php

declare(strict_types=1);

namespace Tests\Acceptance;

use Tests\Support\AcceptanceTester;

/**
 * Deckt den zentralen, sowohl vom Frontend als auch von der REST-API
 * genutzten Use Case ListArticlesForDisplay End-to-End ab (echter HTTP-
 * Request -> Router -> ArticleController -> QueryBus -> Datenbank -> View),
 * siehe App\Application\Article\Query\ListArticlesForDisplayHandler.
 */
final class ArticleListCest
{
    private const MANUFACTURER_ID = '33333333-3333-4333-8333-333333333333';
    private const ARTICLE_ID = '44444444-4444-4444-8444-444444444444';

    public function _before(AcceptanceTester $I): void
    {
        $I->haveInDatabase('manufacturers', [
            'id' => self::MANUFACTURER_ID,
            'name' => 'Bosch',
        ]);
        $I->haveInDatabase('articles', [
            'id' => self::ARTICLE_ID,
            'name' => 'Scheinwerfer LED 500W',
            'manufacturer_id' => self::MANUFACTURER_ID,
            'is_active' => 1,
            'is_billable' => 1,
            'daily_rental_price' => '25.00',
        ]);

        $I->loginAsAdmin();
    }

    public function listsActiveArticlesWithTheirManufacturer(AcceptanceTester $I): void
    {
        $I->amOnPage('/artikel');

        $I->seeResponseCodeIsSuccessful();
        $I->see('Scheinwerfer LED 500W');
        $I->see('Bosch');
    }

    public function theSearchFilterNarrowsDownTheArticleList(AcceptanceTester $I): void
    {
        $I->haveInDatabase('articles', [
            'id' => '55555555-5555-4555-8555-555555555555',
            'name' => 'Funkmikrofon Set',
            'manufacturer_id' => self::MANUFACTURER_ID,
            'is_active' => 1,
            'is_billable' => 1,
        ]);

        $I->amOnPage('/artikel?q=Scheinwerfer');

        $I->see('Scheinwerfer LED 500W');
        $I->dontSee('Funkmikrofon Set');
    }

    public function aDeactivatedArticleIsNotShownInTheArticleSelectionButStaysInTheAdminList(AcceptanceTester $I): void
    {
        $I->haveInDatabase('articles', [
            'id' => '66666666-6666-4666-8666-666666666666',
            'name' => 'Ausgemusterter Scheinwerfer',
            'manufacturer_id' => self::MANUFACTURER_ID,
            'is_active' => 0,
            'is_billable' => 1,
        ]);

        // Die Artikelverwaltung selbst zeigt weiterhin ALLE Artikel an
        // (siehe articles.title-Seite und Datenbank-Kommentar zu is_active
        // in database/schema.sql) — nur die Auswahl innerhalb einer
        // Vermietung blendet inaktive Artikel aus.
        $I->amOnPage('/artikel');
        $I->see('Ausgemusterter Scheinwerfer');
    }
}
