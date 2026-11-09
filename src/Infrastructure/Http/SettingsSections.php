<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

/**
 * Zentrales Verzeichnis aller "/einstellungen/..."-Unterseiten, gruppiert in
 * sechs thematische Bereiche (Firma & Steuern, Dokumente & PDF, Preise &
 * Zahlung, Kommunikation, Lager & Veranstaltungen, System).
 * Einzige Quelle der Wahrheit für zwei Stellen in resources/views/layout.php:
 *
 *  - die Hauptnavigation zeigt dort nur noch VIER Gruppen-Links (statt aller
 *    ~17 Einzelseiten direkt im Menü), je verlinkt auf die erste Seite der
 *    Gruppe;
 *  - jede Einstellungsseite selbst bekommt darunter eine Tab-Leiste mit
 *    allen Seiten IHRER Gruppe (siehe resources/views/settings/_tabs.php),
 *    damit man innerhalb eines Themenbereichs bequem zwischen den
 *    Unterseiten wechseln kann, ohne jedes Mal über das Hauptmenü zu gehen.
 *
 * Jede Controller-Methode, die eine "/einstellungen/..."-Seite rendert,
 * übergibt dafür einen eindeutigen `activeNav`-Wert (Präfix "settings-"),
 * der hier als Seiten-Schlüssel wiederverwendet wird — siehe groupKeyFor().
 * Zwei Seiten desselben Bereichs dürfen bewusst denselben Schlüssel teilen
 * (z. B. die Speicherziele-Liste und ihre Zuordnungen-Unterseite), dann wird
 * in der Tab-Leiste weiterhin derselbe Tab hervorgehoben.
 */
final class SettingsSections
{
    /**
     * @return array<string, array{label: string, pages: list<array{key: string, href: string, label: string}>}>
     */
    public static function groups(): array
    {
        // Neu gegliedert (Nutzerwunsch "zusammengehörige Einstellungen
        // finden"): sechs Themenbereiche statt vier, Steuer- und Bankdaten
        // bei der Firma, alles zur Dokumentgestaltung beisammen, Preise und
        // Zahlung getrennt von der PDF-Gestaltung, technische Einstellungen
        // unter System. 'desc' erscheint auf der Übersichtsseite /einstellungen.
        $page = static fn (string $key, string $href, string $label, string $desc) => ['key' => $key, 'href' => $href, 'label' => $label, 'desc' => $desc];

        return [
            'company' => [
                'label' => 'Firma & Steuern',
                'desc' => 'Firmendaten, Logo, Steuern, Bankverbindungen und Rechtliches',
                'pages' => [
                    $page('settings-firma', '/einstellungen/firma', 'Firma', 'Name, Anschrift, Kontakt, Logo, Favicon und Hintergründe'),
                    $page('settings-rechnung', '/einstellungen/rechnung', 'Steuern & Firmendaten', 'Kleinunternehmer/Umsatzsteuer, Steuernummer, USt-IdNr., Handelsregister, Geschäftsführung, Befreiungshinweis'),
                    $page('settings-bankverbindungen', '/einstellungen/bankverbindungen', t('nav.bank_accounts'), 'Firmenkonten für Dokumente und Kontoführung'),
                    $page('settings-rechtliches', '/einstellungen/rechtliches', t('nav.legal_settings'), 'Vermietbedingungen, Impressum, Datenschutz'),
                    $page('settings-betriebsurlaub', '/einstellungen/betriebsurlaub', 'Betriebsurlaub', 'Zeiträume ohne Ausgabe und Rücknahme'),
                ],
            ],
            'documents' => [
                'label' => 'Dokumente & PDF',
                'desc' => 'Nummern, Vorlagen, Texte, Gestaltung und Ablage der Dokumente',
                'pages' => [
                    $page('settings-nummerierung', '/einstellungen/nummerierung', t('nav.numbering_settings'), 'Nummernkreise für Angebote, Rechnungen usw.'),
                    $page('settings-pdf-design', '/einstellungen/pdf-design', t('nav.pdf_design_settings'), 'Akzentfarbe, Logo-Position, Währungsanzeige'),
                    $page('settings-pdf-layout', '/einstellungen/pdf-layout', t('nav.pdf_layout_settings'), 'Anordnung der Blöcke auf den PDFs'),
                    $page('settings-schriften', '/einstellungen/schriften', t('nav.pdf_fonts'), 'Schriften für Dokumente und Oberfläche'),
                    $page('settings-rechnungsvorlage', '/einstellungen/rechnungsvorlage', t('nav.invoice_pdf_template'), 'Einleitungs- und Schlusstext auf Rechnungen'),
                    $page('settings-angebotsvorlage', '/einstellungen/angebotsvorlage', t('nav.offer_pdf_template'), 'Einleitungs- und Schlusstext auf Angeboten'),
                    $page('settings-starttexte', '/einstellungen/starttexte', t('nav.intro_texts'), 'Vorbelegte Starttexte je Dokumentart'),
                    $page('settings-textvorlagen', '/einstellungen/textvorlagen', t('nav.closing_text_templates'), 'Wiederverwendbare Textbausteine'),
                    $page('settings-speicherziele', '/einstellungen/speicherziele', t('nav.document_storage'), 'Automatische Ablage der PDFs auf Laufwerken'),
                ],
            ],
            'billing' => [
                'label' => 'Preise & Zahlung',
                'desc' => 'Preisregeln, Zahlungsarten, Mahnwesen und Erinnerungen',
                'pages' => [
                    $page('settings-preisstaffelung', '/einstellungen/preisstaffelung', t('nav.price_tiers'), 'Faktor je Einsatztag und Preisrundung'),
                    $page('settings-preise', '/einstellungen/preise', t('nav.pricing_settings'), 'Zubehörpauschale'),
                    $page('settings-ueberziehung', '/einstellungen/ueberziehung', t('nav.overage_settings'), 'Pauschale bei verspäteter Rückgabe'),
                    $page('settings-gutscheine', '/einstellungen/gutscheine', t('nav.voucher_settings'), 'Gültigkeit von Gutscheinen'),
                    $page('settings-zahlungsarten', '/einstellungen/zahlungsarten', t('nav.payment_methods'), 'Zahlungsarten und Zahlungsziele'),
                    $page('settings-mahnwesen', '/einstellungen/mahnwesen', t('nav.dunning_levels'), 'Mahnstufen, Gebühren, automatischer Versand'),
                    $page('settings-rechnungserinnerungen', '/einstellungen/rechnungserinnerungen', t('nav.invoice_due_reminder_settings'), 'Erinnerung vor Fälligkeit'),
                ],
            ],
            'communication' => [
                'label' => t('nav.settings_group_communication'),
                'desc' => 'E-Mail-Versand, Vorlagen, Posteingang, Push und Messenger',
                'pages' => [
                    $page('settings-smtp', '/einstellungen/smtp', t('nav.smtp_settings'), 'Ausgangsserver für E-Mails'),
                    $page('settings-email-vorlagen', '/einstellungen/email-vorlagen', t('nav.email_templates'), 'Texte der versendeten E-Mails'),
                    $page('settings-erinnerungen', '/einstellungen/erinnerungen', t('nav.rental_reminder_settings'), 'Erinnerungen zu Abholung, Rückgabe und Checkliste'),
                    $page('settings-mailserver', '/einstellungen/mailserver', t('nav.mailbox_settings'), 'Belege per E-Mail empfangen'),
                    $page('settings-push', '/einstellungen/push', t('nav.push_settings'), 'Benachrichtigungen auf Geräte'),
                    $page('settings-messenger', '/einstellungen/messenger', t('nav.messaging_settings'), 'WhatsApp, Telegram & Co.'),
                    $page('settings-messenger-vorlagen', '/einstellungen/messenger-vorlagen', t('nav.messaging_templates'), 'Texte der Messenger-Nachrichten'),
                ],
            ],
            'operations' => [
                'label' => 'Lager & Veranstaltungen',
                'desc' => 'Techniker, Gewerke, Veranstaltungs-Audio, Leitungen, Etikettendruck und Kalender',
                'pages' => [
                    $page('settings-techniker', '/einstellungen/techniker', 'Techniker & Gewerke', 'Gewerke und Erinnerungen bei offenen Techniker-Anfragen'),
                    $page('settings-veranstaltungs-audio', '/einstellungen/veranstaltungs-audio', 'Veranstaltungs-Audio', 'Abspielgeräte (Raspberry Pi), Gong und Sprachausgabe für Durchsagen'),
                    $page('settings-leitungen', '/einstellungen/leitungen', 'Leitungen', 'Leitungstypen und Steckverbinder'),
                    $page('settings-etikettendrucker', '/einstellungen/etikettendrucker', t('nav.label_printers'), 'Drucker für Inventur-Etiketten'),
                    $page('settings-kalender', '/einstellungen/kalender', t('nav.calendar_settings'), 'Kalender-Abos (CalDAV/ICS)'),
                ],
            ],
            'system' => [
                'label' => 'System & Integrationen',
                'desc' => 'Sicherheit, Schnittstellen, Datensicherung',
                'pages' => [
                    $page('settings-sicherheit', '/einstellungen/sicherheit', 'Sicherheit', 'Automatische Abmeldung'),
                    $page('settings-integrationen', '/einstellungen/integrationen', t('nav.integration_settings'), 'Google, WhatsApp und Basis-URL'),
                    $page('settings-api', '/einstellungen/api', t('nav.api_settings'), 'REST-API und Zugriffs-Tokens'),
                    $page('settings-ki-werbebilder', '/einstellungen/ki-werbebilder', t('nav.ai_ad_image_settings'), 'KI-Dienst für Werbebilder'),
                    $page('settings-sicherung', '/einstellungen/sicherung', t('nav.backup_settings'), 'Export und Import der Daten'),
                ],
            ],
        ];
    }

    /** Gruppenschlüssel (z. B. "billing"), zu dem $activeNav gehört, oder null außerhalb der Einstellungen. */
    public static function groupKeyFor(string $activeNav): ?string
    {
        foreach (self::groups() as $groupKey => $group) {
            foreach ($group['pages'] as $page) {
                if ($page['key'] === $activeNav) {
                    return $groupKey;
                }
            }
        }

        return null;
    }
}
