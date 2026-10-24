<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

/**
 * Zentrales Verzeichnis aller "/einstellungen/..."-Unterseiten, gruppiert in
 * vier thematische Bereiche (Firma, Abrechnung, Kommunikation, Sonstiges).
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
        return [
            'company' => [
                'label' => t('nav.settings_group_company'),
                'pages' => [
                    ['key' => 'settings-firma', 'href' => '/einstellungen/firma', 'label' => t('nav.company_settings')],
                    ['key' => 'settings-rechtliches', 'href' => '/einstellungen/rechtliches', 'label' => t('nav.legal_settings')],
                    ['key' => 'settings-integrationen', 'href' => '/einstellungen/integrationen', 'label' => t('nav.integration_settings')],
                    ['key' => 'settings-api', 'href' => '/einstellungen/api', 'label' => t('nav.api_settings')],
                    ['key' => 'settings-kalender', 'href' => '/einstellungen/kalender', 'label' => t('nav.calendar_settings')],
                ],
            ],
            'billing' => [
                'label' => t('nav.settings_group_billing'),
                'pages' => [
                    ['key' => 'settings-rechnung', 'href' => '/einstellungen/rechnung', 'label' => t('nav.invoice_settings')],
                    ['key' => 'settings-nummerierung', 'href' => '/einstellungen/nummerierung', 'label' => t('nav.numbering_settings')],
                    ['key' => 'settings-rechnungsvorlage', 'href' => '/einstellungen/rechnungsvorlage', 'label' => t('nav.invoice_pdf_template')],
                    ['key' => 'settings-angebotsvorlage', 'href' => '/einstellungen/angebotsvorlage', 'label' => t('nav.offer_pdf_template')],
                    ['key' => 'settings-pdf-design', 'href' => '/einstellungen/pdf-design', 'label' => t('nav.pdf_design_settings')],
                    ['key' => 'settings-preise', 'href' => '/einstellungen/preise', 'label' => t('nav.pricing_settings')],
                    ['key' => 'settings-gutscheine', 'href' => '/einstellungen/gutscheine', 'label' => t('nav.voucher_settings')],
                    ['key' => 'settings-preisstaffelung', 'href' => '/einstellungen/preisstaffelung', 'label' => t('nav.price_tiers')],
                    ['key' => 'settings-ueberziehung', 'href' => '/einstellungen/ueberziehung', 'label' => t('nav.overage_settings')],
                    ['key' => 'settings-mahnwesen', 'href' => '/einstellungen/mahnwesen', 'label' => t('nav.dunning_levels')],
                ],
            ],
            'communication' => [
                'label' => t('nav.settings_group_communication'),
                'pages' => [
                    ['key' => 'settings-smtp', 'href' => '/einstellungen/smtp', 'label' => t('nav.smtp_settings')],
                    ['key' => 'settings-email-vorlagen', 'href' => '/einstellungen/email-vorlagen', 'label' => t('nav.email_templates')],
                    ['key' => 'settings-mailserver', 'href' => '/einstellungen/mailserver', 'label' => t('nav.mailbox_settings')],
                    ['key' => 'settings-push', 'href' => '/einstellungen/push', 'label' => t('nav.push_settings')],
                    ['key' => 'settings-messenger', 'href' => '/einstellungen/messenger', 'label' => t('nav.messaging_settings')],
                    ['key' => 'settings-messenger-vorlagen', 'href' => '/einstellungen/messenger-vorlagen', 'label' => t('nav.messaging_templates')],
                ],
            ],
            'other' => [
                'label' => t('nav.settings_group_other'),
                'pages' => [
                    ['key' => 'settings-etikettendrucker', 'href' => '/einstellungen/etikettendrucker', 'label' => t('nav.label_printers')],
                    ['key' => 'settings-speicherziele', 'href' => '/einstellungen/speicherziele', 'label' => t('nav.document_storage')],
                    ['key' => 'settings-erinnerungen', 'href' => '/einstellungen/erinnerungen', 'label' => t('nav.rental_reminder_settings')],
                    ['key' => 'settings-sicherung', 'href' => '/einstellungen/sicherung', 'label' => t('nav.backup_settings')],
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
