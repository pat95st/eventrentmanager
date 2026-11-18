<?php

declare(strict_types=1);

namespace App\Domain\Settings;

/**
 * Every confirmation ("Rückfrage") of the main application, identified by a
 * stable key. Forms carry the key as data-confirm-key next to data-confirm;
 * confirmations asked from JavaScript pass it to window.confirmGate().
 *
 * Holding Shift skips a confirmation unless its key is "required". Each
 * entry has a factory default; administrators change it under
 * Einstellungen › Sicherheit (see SecuritySettings::$confirmOverrides).
 * tests/unit/Domain/Settings/ConfirmCatalogTest.php fails when a form has no
 * entry here or an entry is no longer used.
 */
final class ConfirmCatalog
{
    /** key => [group, label, required by default] */
    private const ENTRIES = [
        // Veranstaltungen
        'rentals.issue.undo' => ['Veranstaltungen', 'Ausgabe rückgängig machen', false],
        'rentals.create_invoice' => ['Veranstaltungen', 'Rechnung aus Veranstaltung erstellen', false],
        'rentals.delete' => ['Veranstaltungen', 'Veranstaltung löschen', false],
        'rentals.bundle.delete' => ['Veranstaltungen', 'Gebuchtes Bundle entfernen', false],
        'rentals.set.delete' => ['Veranstaltungen', 'Gebuchtes Set entfernen', false],
        'rentals.item.delete' => ['Veranstaltungen', 'Veranstaltungsposten entfernen', false],
        'rentals.customer_checklist.renew_link' => ['Veranstaltungen', 'Link der Kundencheckliste erneuern', false],
        'rentals.voucher.remove' => ['Veranstaltungen', 'Gutschein von der Veranstaltung entfernen', false],
        'rentals.return.undo' => ['Veranstaltungen', 'Rückgabe rückgängig machen', false],
        'rentals.technician.delete' => ['Veranstaltungen', 'Techniker-Zuordnung entfernen', false],
        'rentals.series.detach' => ['Veranstaltungen', 'Termin aus der Serie lösen', false],
        'rentals.series.change_rule' => ['Veranstaltungen', 'Rhythmus einer Serie ändern', false],
        'rentals.series.end' => ['Veranstaltungen', 'Serie beenden', true],
        'rentals.tour.make' => ['Veranstaltungen', 'Veranstaltung als Tour planen', false],
        'rentals.tour.revert' => ['Veranstaltungen', 'Tour-Planung entfernen', false],
        'rentals.tour.stop.delete' => ['Veranstaltungen', 'Tourstop oder Zwischenstopp einer Tour löschen', false],
        'rentals.technician.decline' => ['Veranstaltungen', 'Als Techniker für eine Veranstaltung absagen', false],
        'partners.request.cancel' => ['Veranstaltungen', 'Partner-Anfrage stornieren', false],
        'rentals.company_holiday' => ['Veranstaltungen', 'Veranstaltung trotz Betriebsurlaub speichern', false],
        // Artikel & Lager
        'inventory_numbers.sell' => ['Artikel & Lager', 'Exemplar aus dem Bestand verkaufen', false],
        'cases.delete' => ['Artikel & Lager', 'Case löschen', false],
        'cases.inventory_number.delete' => ['Artikel & Lager', 'Case-Exemplar löschen', false],
        'cases.content.delete' => ['Artikel & Lager', 'Case-Inhalt entfernen', false],
        'cases.image.delete' => ['Artikel & Lager', 'Case-Bild löschen', false],
        'rma.delete' => ['Artikel & Lager', 'RMA-Vorgang löschen', false],
        'rma.document.delete' => ['Artikel & Lager', 'RMA-Dokument löschen', false],
        'rma.photo.delete' => ['Artikel & Lager', 'RMA-Versandfoto löschen', false],
        'articles.deactivate' => ['Artikel & Lager', 'Artikel deaktivieren', false],
        'articles.delete' => ['Artikel & Lager', 'Artikel löschen', false],
        'articles.image.delete' => ['Artikel & Lager', 'Artikelbild löschen', false],
        'articles.ad_image.delete' => ['Artikel & Lager', 'Werbebild löschen', false],
        'articles.notice_document.remove' => ['Artikel & Lager', 'Hinweisdokument vom Artikel entfernen', false],
        'articles.firmware.delete' => ['Artikel & Lager', 'Firmware-Version löschen', false],
        'articles.inventory_number.delete' => ['Artikel & Lager', 'Inventurnummer löschen', false],
        'articles.accessory.delete' => ['Artikel & Lager', 'Zubehör entfernen', false],
        'articles.pre_issue_check.delete' => ['Artikel & Lager', 'Vor-Ausgabe-Check löschen', false],
        'warehouses.delete' => ['Artikel & Lager', 'Lagerort löschen', false],
        'electrical_inspections.delete' => ['Artikel & Lager', 'Elektroprüfung löschen', false],
        'categories.delete' => ['Artikel & Lager', 'Kategorie löschen', false],
        'sets.delete' => ['Artikel & Lager', 'Set löschen', false],
        'racks.delete' => ['Artikel & Lager', 'Rack löschen', false],
        'racks.exemplar.delete' => ['Artikel & Lager', 'Rack-Exemplar löschen', false],
        'racks.slot.delete' => ['Artikel & Lager', 'Rack-Einbau entfernen', false],
        // Stammdaten
        'manufacturers.delete' => ['Stammdaten', 'Hersteller löschen', false],
        'notice_documents.delete' => ['Stammdaten', 'Hinweisdokument löschen', false],
        'customers.delete' => ['Stammdaten', 'Kunde löschen', false],
        'labor_time_articles.delete' => ['Stammdaten', 'Arbeitszeit-Artikel löschen', false],
        'venues.stage_riser.delete' => ['Stammdaten', 'Bühnenpodest löschen', false],
        'venues.delete' => ['Stammdaten', 'Veranstaltungsort löschen', false],
        'venues.image.delete' => ['Stammdaten', 'Bild eines Veranstaltungsorts löschen', false],
        'venues.power_connection.delete' => ['Stammdaten', 'Stromanschluss löschen', false],
        'venues.stage.delete' => ['Stammdaten', 'Bühne löschen', false],
        'suppliers.delete' => ['Stammdaten', 'Lieferant löschen', false],
        'vehicles.delete' => ['Stammdaten', 'Fahrzeug löschen', false],
        // Dokumente & Buchhaltung
        'invoices.send' => ['Dokumente & Buchhaltung', 'Rechnung versenden', true],
        'invoices.cancel' => ['Dokumente & Buchhaltung', 'Rechnung stornieren', true],
        'invoices.delete' => ['Dokumente & Buchhaltung', 'Rechnung löschen', true],
        'vouchers.delete' => ['Dokumente & Buchhaltung', 'Gutschein löschen', false],
        'offers.delete' => ['Dokumente & Buchhaltung', 'Angebot löschen', false],
        'offers.create_invoice' => ['Dokumente & Buchhaltung', 'Rechnung aus Angebot erstellen', false],
        'offers.convert' => ['Dokumente & Buchhaltung', 'Angebot in Veranstaltung umwandeln', false],
        'order_confirmations.delete' => ['Dokumente & Buchhaltung', 'Auftragsbestätigung löschen', false],
        'order_confirmations.cancel' => ['Dokumente & Buchhaltung', 'Auftragsbestätigung stornieren', false],
        'receipts.delete' => ['Dokumente & Buchhaltung', 'Beleg löschen', false],
        'invoice_corrections.issue' => ['Dokumente & Buchhaltung', 'Rechnungskorrektur ausstellen', true],
        'invoice_corrections.delete' => ['Dokumente & Buchhaltung', 'Rechnungskorrektur löschen', true],
        'purchase_orders.mark_ordered' => ['Dokumente & Buchhaltung', 'Bestellung als bestellt markieren', false],
        'purchase_orders.delete' => ['Dokumente & Buchhaltung', 'Bestellung löschen', false],
        'purchase_orders.cancel' => ['Dokumente & Buchhaltung', 'Bestellung stornieren', false],
        'accounts.transaction.delete' => ['Dokumente & Buchhaltung', 'Kontobuchung löschen', false],
        // Minutenprogramme & Veranstaltungs-Audio
        'event_audio.file.delete' => ['Minutenprogramme & Veranstaltungs-Audio', 'Audio-Datei löschen', false],
        'event_audio.schedule.repeat' => ['Minutenprogramme & Veranstaltungs-Audio', 'Geplanten Einspieler erneut abspielen', false],
        'event_audio.schedule.delete' => ['Minutenprogramme & Veranstaltungs-Audio', 'Geplanten Einspieler löschen', false],
        'event_audio.logo.delete' => ['Minutenprogramme & Veranstaltungs-Audio', 'Veranstaltungs-Logo entfernen', false],
        'event_audio.login.delete' => ['Minutenprogramme & Veranstaltungs-Audio', 'Veranstalter-Zugang löschen', false],
        'event_audio.repeat' => ['Minutenprogramme & Veranstaltungs-Audio', 'Einspieler aus dem Verlauf erneut abspielen', false],
        'minute_program_templates.delete' => ['Minutenprogramme & Veranstaltungs-Audio', 'Minutenprogramm-Vorlage löschen', false],
        'minute_program_templates.item.delete' => ['Minutenprogramme & Veranstaltungs-Audio', 'Punkt einer Minutenprogramm-Vorlage löschen', false],
        'minute_programs.delete' => ['Minutenprogramme & Veranstaltungs-Audio', 'Minutenprogramm löschen', false],
        'minute_programs.item.delete' => ['Minutenprogramme & Veranstaltungs-Audio', 'Programmpunkt löschen', false],
        // Benutzerverwaltung
        'users.impersonate' => ['Benutzerverwaltung', 'Als anderer Benutzer anmelden', true],
        'users.two_factor.reset' => ['Benutzerverwaltung', 'Zwei-Faktor-Anmeldung eines Benutzers zurücksetzen', true],
        'users.delete' => ['Benutzerverwaltung', 'Benutzer löschen', true],
        'users.sessions.revoke_all' => ['Benutzerverwaltung', 'Alle Sitzungen eines Benutzers beenden', true],
        'users.sessions.revoke' => ['Benutzerverwaltung', 'Einzelne Sitzung eines Benutzers beenden', true],
        // Eigenes Profil
        'profile.dashboard.reset' => ['Eigenes Profil', 'Eigenes Dashboard zurücksetzen', false],
        'profile.dashboard.apply_template' => ['Eigenes Profil', 'Dashboard-Vorlage übernehmen', false],
        'profile.sessions.revoke_others' => ['Eigenes Profil', 'Alle anderen eigenen Sitzungen beenden', true],
        'profile.sessions.revoke' => ['Eigenes Profil', 'Einzelne eigene Sitzung beenden', true],
        'profile.caldav.delete' => ['Eigenes Profil', 'CalDAV-Zugang löschen', false],
        'profile.two_factor.disable' => ['Eigenes Profil', 'Zwei-Faktor-Anmeldung deaktivieren', true],
        'profile.trusted_devices.revoke' => ['Eigenes Profil', 'Vertraute Geräte widerrufen', true],
        'profile.carddav.delete' => ['Eigenes Profil', 'CardDAV-Zugang löschen', false],
        'profile.passkey.remove' => ['Eigenes Profil', 'Passkey entfernen', true],
        'profile.push.remove' => ['Eigenes Profil', 'Push-Gerät entfernen', false],
        // Einstellungen
        'settings.event_audio.player.token' => ['Einstellungen', 'Audio-Gerät: Token neu erzeugen', false],
        'settings.event_audio.player.delete' => ['Einstellungen', 'Audio-Gerät löschen', false],
        'settings.event_audio.gong.remove' => ['Einstellungen', 'Gong entfernen', false],
        'settings.event_audio.deepl_key.remove' => ['Einstellungen', 'DeepL-Schlüssel entfernen', false],
        'settings.label_printers.delete' => ['Einstellungen', 'Etikettendrucker löschen', false],
        'settings.email_templates.reset' => ['Einstellungen', 'E-Mail-Vorlage zurücksetzen', false],
        'settings.cables.type.delete' => ['Einstellungen', 'Leitungstyp löschen', false],
        'settings.cables.connector.delete' => ['Einstellungen', 'Steckverbinder löschen', false],
        'settings.mailbox.sender.delete' => ['Einstellungen', 'Erlaubten Absender löschen', false],
        'settings.calendars.delete' => ['Einstellungen', 'Kalender löschen', false],
        'settings.calendars.signature.delete' => ['Einstellungen', 'Kalender-Signatur löschen', false],
        'settings.company_holidays.delete' => ['Einstellungen', 'Betriebsurlaub löschen', false],
        'settings.messaging_templates.reset' => ['Einstellungen', 'Messenger-Vorlage zurücksetzen', false],
        'settings.text_templates.delete' => ['Einstellungen', 'Textvorlage löschen', false],
        'settings.text_templates.folder.delete' => ['Einstellungen', 'Textvorlagen-Ordner löschen', false],
        'settings.networks.delete' => ['Einstellungen', 'Netzwerk löschen', false],
        'settings.networks.category.delete' => ['Einstellungen', 'Adressbereich einer Kategorie löschen', false],
        'settings.trades.delete' => ['Einstellungen', 'Gewerk löschen', false],
        'settings.dashboard_templates.delete' => ['Einstellungen', 'Dashboard-Vorlage löschen', false],
        'settings.partner.reject' => ['Einstellungen', 'Partnerschaft ablehnen', false],
        'settings.partner.end' => ['Einstellungen', 'Partnerschaft beenden', false],
        'settings.partner.delete' => ['Einstellungen', 'Partner löschen', false],
        'settings.integrations.google_merchant.delete' => ['Einstellungen', 'Google-Merchant-Zugangsdaten löschen', false],
        'settings.integrations.google_business.disconnect' => ['Einstellungen', 'Google-Business-Profil trennen', false],
        'settings.dunning_levels.delete' => ['Einstellungen', 'Mahnstufe löschen', false],
        'settings.displays.token' => ['Einstellungen', 'Display: Token neu erzeugen', false],
        'settings.displays.delete' => ['Einstellungen', 'Display löschen', false],
        'settings.payment_methods.delete' => ['Einstellungen', 'Zahlungsart löschen', false],
        'settings.digital_protocols.delete' => ['Einstellungen', 'Digitales Protokoll löschen', false],
        'settings.pdf_fonts.delete' => ['Einstellungen', 'PDF-Schrift löschen', false],
        'settings.api.token.delete' => ['Einstellungen', 'API-Token löschen', true],
        'settings.document_storage.assignment.delete' => ['Einstellungen', 'Speicherziel-Zuordnung löschen', false],
        'settings.document_storage.target.delete' => ['Einstellungen', 'Speicherziel löschen', false],
        'settings.document_storage.target.disconnect' => ['Einstellungen', 'Speicherziel trennen', false],
        'settings.company.logo.delete' => ['Einstellungen', 'Firmenlogo löschen', false],
        'settings.company.logo_dark.delete' => ['Einstellungen', 'Firmenlogo (dunkel) löschen', false],
        'settings.company.favicon.delete' => ['Einstellungen', 'Favicon löschen', false],
        'settings.company.background.delete' => ['Einstellungen', 'Hintergrundbild löschen', false],
        'settings.bank_accounts.delete' => ['Einstellungen', 'Bankverbindung löschen', false],
        'settings.revenue_goals.delete' => ['Einstellungen', 'Umsatzziel löschen', false],
        'settings.online_payments.credentials.delete' => ['Einstellungen', 'Zugangsdaten für Online-Zahlung löschen', false],
        'settings.security.confirm.reset' => ['Einstellungen', 'Rückfragen auf Standard zurücksetzen', false],
    ];

    /** @return list<string> */
    public static function keys(): array
    {
        return array_keys(self::ENTRIES);
    }

    public static function has(string $key): bool
    {
        return isset(self::ENTRIES[$key]);
    }

    public static function requiredByDefault(string $key): bool
    {
        return self::ENTRIES[$key][2] ?? false;
    }

    /** @return list<string> */
    public static function defaultRequiredKeys(): array
    {
        return array_keys(array_filter(self::ENTRIES, static fn (array $entry): bool => $entry[2]));
    }

    /**
     * Entries in display order, for the settings page.
     *
     * @return array<string, array<string, array{label: string, requiredByDefault: bool}>> group => key => entry
     */
    public static function grouped(): array
    {
        $groups = [];
        foreach (self::ENTRIES as $key => [$group, $label, $required]) {
            $groups[$group][$key] = ['label' => $label, 'requiredByDefault' => $required];
        }

        return $groups;
    }
}
