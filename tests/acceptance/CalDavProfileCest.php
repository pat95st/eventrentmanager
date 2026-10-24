<?php

declare(strict_types=1);

namespace Tests\Acceptance;

use Tests\Support\AcceptanceTester;

/**
 * Selbstverwaltung der CalDAV-App-Passwörter (Profil → CalDAV) und der
 * .mobileconfig-Download, siehe ProfileController. Der Upload-Teil deckt
 * bewusst genau den Erfolgspfad ab, den
 * Tests\Unit\Application\Settings\UploadCalDavSigningCertificateHandlerTest
 * NICHT abdecken kann (move_uploaded_file() funktioniert nur bei einem
 * echten HTTP-Multipart-Request, siehe dort).
 */
final class CalDavProfileCest
{
    public function _before(AcceptanceTester $I): void
    {
        $I->loginAsAdmin();
    }

    public function canCreateAndDeleteAnAppPassword(AcceptanceTester $I): void
    {
        $I->amOnPage('/profil/caldav');
        $I->submitForm('form[action="/profil/caldav"]', ['name' => 'iPhone']);

        $I->seeCurrentUrlEquals('/profil/caldav');
        $I->see('iPhone');
        $I->seeInDatabase('caldav_credentials', ['name' => 'iPhone']);

        $credentialId = $I->grabFromDatabase('caldav_credentials', 'id', ['name' => 'iPhone']);

        $I->submitForm('form[action="/profil/caldav/' . $credentialId . '/loeschen"]', []);
        $I->dontSeeInDatabase('caldav_credentials', ['id' => $credentialId]);
    }

    public function rejectsAnEmptyNameForANewAppPassword(AcceptanceTester $I): void
    {
        $I->amOnPage('/profil/caldav');
        $I->submitForm('form[action="/profil/caldav"]', ['name' => '']);

        $I->dontSeeInDatabase('caldav_credentials', ['user_id' => $this->adminId($I)]);
        $I->see('Bitte einen Namen');
    }

    public function downloadingTheMobileconfigProfileCreatesANewAppPasswordAndReturnsASignedProfile(AcceptanceTester $I): void
    {
        $before = (int) $I->grabNumRecords('caldav_credentials', []);

        $I->amOnPage('/profil/caldav');
        $I->submitForm('form[action="/profil/caldav/profil.mobileconfig"]', []);

        $I->seeResponseCodeIsSuccessful();
        $I->seeHttpHeader('Content-Type', 'application/x-apple-aspen-config');

        $after = (int) $I->grabNumRecords('caldav_credentials', []);
        $I->assertSame($before + 1, $after, 'Der Download soll genau ein neues App-Passwort anlegen.');

        // Ohne hochgeladenes Zertifikat greift automatisch das
        // selbstsignierte Fallback-Zertifikat (siehe
        // CalDavSigningCertificateProvider) — die Signatur-Einstellungen
        // müssen danach entsprechend befüllt sein.
        $I->seeInDatabase('caldav_signing_settings', ['id' => 1, 'is_self_signed' => 1]);
    }

    public function anAdministratorCanUploadAndRemoveACustomSigningCertificate(AcceptanceTester $I): void
    {
        $I->amOnPage('/einstellungen/kalender');
        $I->attachFile('certificate', 'certs/test-signing-cert.pem');
        $I->attachFile('private_key', 'certs/test-signing-key.pem');
        $I->click('Zertifikat hochladen');

        $I->seeCurrentUrlEquals('/einstellungen/kalender');
        $I->seeInDatabase('caldav_signing_settings', ['id' => 1, 'is_self_signed' => 0]);

        $I->amOnPage('/profil/caldav');
        $I->submitForm('form[action="/profil/caldav/profil.mobileconfig"]', []);
        $I->seeResponseCodeIsSuccessful();

        // Zertifikat wieder entfernen -> fällt zurück auf selbstsigniert.
        // (Kein seeInDatabase(..., ['certificate_filename' => null]) hier,
        // da eine WHERE-Bedingung "= NULL" in SQL nie zutrifft — stattdessen
        // den Wert holen und in PHP auf null prüfen.)
        $I->amOnPage('/einstellungen/kalender');
        $I->click('Eigenes Zertifikat entfernen');

        $certificateFilename = $I->grabFromDatabase('caldav_signing_settings', 'certificate_filename', ['id' => 1]);
        $I->assertNull($certificateFilename);
    }

    private function adminId(AcceptanceTester $I): string
    {
        return (string) $I->grabFromDatabase('users', 'id', ['email' => \Tests\Support\Helper\Acceptance::ADMIN_EMAIL]);
    }
}
