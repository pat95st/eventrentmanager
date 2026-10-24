<?php

declare(strict_types=1);

namespace Tests\Acceptance;

use Tests\Support\AcceptanceTester;
use Tests\Support\Helper\Acceptance;

/**
 * Prüft die Anmeldung inkl. der erzwungenen Erstanmeldungs-Passwortänderung
 * (siehe database/schema.sql für den Seed-Administrator und
 * src/Infrastructure/Http/Auth/AuthGuard.php für die Zwangsumleitung).
 */
final class LoginCest
{
    public function cannotAccessAProtectedPageWithoutBeingLoggedIn(AcceptanceTester $I): void
    {
        $I->amOnPage('/artikel');
        $I->seeCurrentUrlEquals('/login');
    }

    public function showsAnErrorForWrongCredentials(AcceptanceTester $I): void
    {
        $I->amOnPage('/login');
        $I->submitForm('form', [
            'email' => Acceptance::ADMIN_EMAIL,
            'password' => 'ein-falsches-passwort',
        ]);

        $I->seeCurrentUrlEquals('/login');
        $I->see('ist falsch');
    }

    public function logsInAndIsForcedToChangeThePasswordOnFirstLogin(AcceptanceTester $I): void
    {
        $I->amOnPage('/login');
        $I->submitForm('form', [
            'email' => Acceptance::ADMIN_EMAIL,
            'password' => Acceptance::ADMIN_INITIAL_PASSWORD,
        ]);

        $I->seeCurrentUrlEquals('/profil/passwort');
        $I->see('Passwort');
    }

    public function canReachAProtectedPageAfterCompletingTheFullLoginFlow(AcceptanceTester $I): void
    {
        $I->loginAsAdmin();
        $I->amOnPage('/profil/caldav');

        $I->seeCurrentUrlEquals('/profil/caldav');
        $I->see('CalDAV');
    }

    public function canLogOutAndIsThenAskedToLogInAgain(AcceptanceTester $I): void
    {
        $I->loginAsAdmin();
        $I->amOnPage('/artikel');
        $I->submitForm('form[action="/logout"]', []);

        $I->amOnPage('/artikel');
        $I->seeCurrentUrlEquals('/login');
    }
}
