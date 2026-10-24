<?php

declare(strict_types=1);

namespace Tests\Acceptance;

use Tests\Support\AcceptanceTester;

/**
 * Verwaltung der CalDAV-Kalender unter Einstellungen → Kalender (siehe
 * src/Infrastructure/Http/Controllers/CalendarSettingsController.php). Der
 * Veranstaltungskalender selbst kommt bereits aus database/schema.sql
 * (fest eingebaut, feste ID), ein Vermietungskalender braucht mindestens
 * ein Filterkriterium — dafür wird hier direkt ein Lagerort-Datensatz in
 * die Test-Datenbank eingefügt (siehe Db-Modul in tests/acceptance.suite.yml),
 * weil die Auswahlfelder im Formular <select>-Elemente sind und daher einen
 * tatsächlich existierenden Datensatz brauchen.
 */
final class CalendarSettingsCest
{
    private const EVENT_CALENDAR_ID = '00000000-0000-4000-8000-000000000001';
    private const WAREHOUSE_ID = '11111111-1111-4111-8111-111111111111';

    public function _before(AcceptanceTester $I): void
    {
        $I->haveInDatabase('warehouses', [
            'id' => self::WAREHOUSE_ID,
            'name' => 'Hauptlager',
            'street' => 'Lagerstraße 1',
            'postal_code' => '12345',
            'city' => 'Teststadt',
        ]);

        $I->loginAsAdmin();
    }

    public function showsTheBuiltInEventCalendarWhichCannotBeDeleted(AcceptanceTester $I): void
    {
        $I->amOnPage('/einstellungen/kalender');

        $I->see('Kalender');
        // Der Veranstaltungskalender kommt aus database/schema.sql standardmäßig
        // DEAKTIVIERT (enabled = 0), zeigt also das "aktivieren"-Formular.
        $I->seeElement('form[action="/einstellungen/kalender/' . self::EVENT_CALENDAR_ID . '/aktivieren"]');
        // Fest eingebaut — im Gegensatz zu Vermietungskalendern gibt es für
        // ihn KEIN Lösch-Formular (siehe DeleteCalendarHandler und
        // settings/calendars.php).
        $I->dontSeeElement('form[action="/einstellungen/kalender/' . self::EVENT_CALENDAR_ID . '/loeschen"]');
    }

    public function canCreateEnableAndDeleteARentalCalendar(AcceptanceTester $I): void
    {
        $I->amOnPage('/einstellungen/kalender');
        $I->submitForm('form[action="/einstellungen/kalender"]', [
            'name' => 'Kalender Hauptlager',
            'warehouse_id' => self::WAREHOUSE_ID,
        ]);

        $I->seeCurrentUrlEquals('/einstellungen/kalender');
        $I->see('Kalender Hauptlager');
        $I->seeInDatabase('calendars', ['name' => 'Kalender Hauptlager', 'kind' => 'rental', 'enabled' => 0]);

        $calendarId = $I->grabFromDatabase('calendars', 'id', ['name' => 'Kalender Hauptlager']);

        // Neu angelegte Vermietungskalender starten deaktiviert (siehe
        // CreateCalendarHandler) — erst aktivieren, dann wieder löschen.
        $I->submitForm('form[action="/einstellungen/kalender/' . $calendarId . '/aktivieren"]', []);
        $I->seeInDatabase('calendars', ['id' => $calendarId, 'enabled' => 1]);

        $I->submitForm('form[action="/einstellungen/kalender/' . $calendarId . '/loeschen"]', []);
        $I->dontSeeInDatabase('calendars', ['id' => $calendarId]);
    }

    public function rejectsCreatingACalendarWithoutAnyFilterCriterion(AcceptanceTester $I): void
    {
        $I->amOnPage('/einstellungen/kalender');
        $I->submitForm('form[action="/einstellungen/kalender"]', [
            'name' => 'Kalender ohne Filter',
        ]);

        $I->dontSeeInDatabase('calendars', ['name' => 'Kalender ohne Filter']);
        $I->see('Filterkriterium');
    }

    public function canGrantCalendarAccessToASpecificUser(AcceptanceTester $I): void
    {
        $userId = '22222222-2222-4222-8222-222222222222';
        $I->haveInDatabase('users', [
            'id' => $userId,
            'email' => 'mitarbeiter@example.test',
            'permissions' => 'ausgabe_ruecknahme',
        ]);

        $I->amOnPage('/einstellungen/kalender?zugriff=' . self::EVENT_CALENDAR_ID);
        $I->submitForm('form[action="/einstellungen/kalender/' . self::EVENT_CALENDAR_ID . '/zugriff"]', [
            'user_ids' => [$userId],
        ]);

        $I->seeInDatabase('calendar_access', ['calendar_id' => self::EVENT_CALENDAR_ID, 'user_id' => $userId]);
    }
}
