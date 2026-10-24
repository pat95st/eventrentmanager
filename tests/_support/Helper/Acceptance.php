<?php

declare(strict_types=1);

namespace Tests\Support\Helper;

use Codeception\Module;
use Codeception\Module\PhpBrowser;

/**
 * Meldet sich als der von database/schema.sql geseedete Standard-
 * Administrator an (admin@materialverleih.local) UND erledigt dabei gleich
 * die erzwungene Erstanmeldungs-Passwortänderung
 * (AuthGuard::FORCED_PASSWORD_CHANGE_PATH, siehe
 * src/Infrastructure/Http/Auth/AuthGuard.php) — ohne dieses Hilfsmittel
 * müsste jeder einzelne Acceptance-Test, der eine angemeldete Sitzung
 * braucht, denselben zweistufigen Ablauf wiederholen, da das Db-Modul die
 * Datenbank (und damit must_change_password = 1 für den Seed-Admin) vor
 * JEDEM Test neu aus database/schema.sql lädt (siehe
 * tests/acceptance.suite.yml).
 */
class Acceptance extends Module
{
    public const ADMIN_EMAIL = 'admin@materialverleih.local';
    public const ADMIN_INITIAL_PASSWORD = 'ChangeMe!2026';
    public const ADMIN_TEST_PASSWORD = 'TestPasswort2026!';

    public function loginAsAdmin(): void
    {
        $browser = $this->phpBrowser();

        $browser->amOnPage('/login');
        $browser->submitForm('form', [
            'email' => self::ADMIN_EMAIL,
            'password' => self::ADMIN_INITIAL_PASSWORD,
        ]);

        // Erstanmeldung: erzwungene Passwortänderung, siehe Klassendocblock.
        // WICHTIG: bewusst NICHT der bare 'form'-Selektor — diese Seite
        // läuft (anders als /login, eine eigenständige Seite ohne Layout)
        // durch View::page() und damit durch layout.php, dessen Kopfzeile
        // bereits VOR dem eigentlichen Seiteninhalt ein eigenes <form
        // action="/logout"> enthält. Ein bare 'form' würde also fälschlich
        // DAS Formular treffen und den Test-User ausloggen statt das
        // Passwort zu ändern.
        $browser->seeCurrentUrlEquals('/profil/passwort');
        $browser->submitForm('form[action="/profil/passwort"]', [
            'current_password' => self::ADMIN_INITIAL_PASSWORD,
            'new_password' => self::ADMIN_TEST_PASSWORD,
        ]);

        // ProfileController::changePassword() leitet nach Erfolg auf '/'
        // weiter, DashboardController::index() leitet von dort sofort
        // weiter auf '/artikel' (siehe dort) — PhpBrowser folgt beiden
        // Redirects automatisch, die Anmeldung endet also auf '/artikel'.
        $browser->seeCurrentUrlEquals('/artikel');
    }

    private function phpBrowser(): PhpBrowser
    {
        /** @var PhpBrowser $browser */
        $browser = $this->getModule('PhpBrowser');

        return $browser;
    }
}
