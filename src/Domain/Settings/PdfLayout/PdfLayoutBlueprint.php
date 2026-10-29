<?php

declare(strict_types=1);

namespace App\Domain\Settings\PdfLayout;

/**
 * Zentrales Verzeichnis: welche Blöcke bietet der visuelle Editor für
 * welchen Dokumenttyp an (siehe PdfDocumentType), mit Anzeigename und
 * einer ungefähren Default-Box für die erste Anzeige im Editor, bevor
 * der Administrator etwas verschoben hat.
 *
 * Die Blöcke orientieren sich an dem, was die jeweilige *PdfGenerator-
 * Klasse heute bereits zeichnet (siehe deren drawHeader()-Methode) —
 * reine Freitext-/Tabellen-/Summenbereiche (Positionstabelle,
 * Summenblock, Bankzeile, Vermietbedingungen) sowie die DIN-5008-
 * Falzmarken bleiben bewusst fest, siehe Projektentscheidung dazu.
 * "intro_text"/"closing_text" gibt es nur dort, wo bereits ein
 * entsprechendes *PdfSettings-Freitextfeld existiert (Rechnung/Angebot) —
 * bei der Auftragsbestätigung fehlt dieses Freitextfeld bislang komplett
 * und wird hier NICHT ergänzt (das wäre eine neue Text-Funktion, kein
 * Positionierungs-Feature, siehe Architektur-Dokument).
 */
final class PdfLayoutBlueprint
{
    /** @return array<string, PdfLayoutElementDefinition> keyed nach element_key, in Zeichen-Reihenfolge */
    public static function elementsFor(PdfDocumentType $type): array
    {
        $elements = match ($type) {
            PdfDocumentType::Invoice, PdfDocumentType::Offer => [
                new PdfLayoutElementDefinition('logo', 'Logo', 14.0, 14.0, 42.0, false),
                new PdfLayoutElementDefinition('title', 'Dokumenttitel', 14.0, 30.0, null, true, 18.0),
                new PdfLayoutElementDefinition('info_lines', 'Rechnungsinfo-Block', 150.0, 30.0, 46.0, true, 10.0),
                new PdfLayoutElementDefinition('return_address', 'Absenderzeile (Fensterumschlag)', 20.0, 44.0, 85.0, true, 7.0),
                new PdfLayoutElementDefinition('customer_address', 'Kundenadresse', 20.0, 50.0, 85.0, true, 10.0),
                new PdfLayoutElementDefinition('intro_text', 'Einleitungstext', 14.0, 95.0, 182.0, false),
                new PdfLayoutElementDefinition('closing_text', 'Schlusstext', 14.0, 230.0, 182.0, false),
            ],
            PdfDocumentType::OrderConfirmation => [
                new PdfLayoutElementDefinition('logo', 'Logo', 14.0, 14.0, 42.0, false),
                new PdfLayoutElementDefinition('title', 'Dokumenttitel', 14.0, 30.0, null, true, 18.0),
                new PdfLayoutElementDefinition('info_lines', 'Auftragsinfo-Block', 150.0, 30.0, 46.0, true, 10.0),
                new PdfLayoutElementDefinition('return_address', 'Absenderzeile (Fensterumschlag)', 20.0, 44.0, 85.0, true, 7.0),
                new PdfLayoutElementDefinition('customer_address', 'Kundenadresse', 20.0, 50.0, 85.0, true, 10.0),
            ],
            PdfDocumentType::Dunning => [
                new PdfLayoutElementDefinition('title', 'Dokumenttitel', 17.0, 20.0, null, true, 16.0),
                new PdfLayoutElementDefinition('return_address', 'Absenderzeile (Fensterumschlag)', 17.0, 18.0, 85.0, true, 7.5),
                new PdfLayoutElementDefinition('customer_address', 'Kundenadresse', 17.0, 30.0, 90.0, true, 10.0),
            ],
            PdfDocumentType::PackingList => [
                new PdfLayoutElementDefinition('logo', 'Logo', 14.0, 14.0, 42.0, false),
                new PdfLayoutElementDefinition('title', 'Dokumenttitel', 14.0, 30.0, null, true, 18.0),
                new PdfLayoutElementDefinition('info_lines', 'Veranstaltungsinfo-Block', 14.0, 40.0, 180.0, true, 10.0),
            ],
            PdfDocumentType::PriceList => [
                // Nutzerwunsch: Logo rechts oben (Standard = rechter Rand,
                // 42 mm breit wie bei den übrigen Dokumenten).
                new PdfLayoutElementDefinition('logo', 'Logo', 154.0, 14.0, 42.0, false),
                new PdfLayoutElementDefinition('title', 'Dokumenttitel', 14.0, 14.0, null, true, 18.0),
            ],
        };

        $byKey = [];
        foreach ($elements as $element) {
            $byKey[$element->key] = $element;
        }

        return $byKey;
    }

    /** @return list<string> gültige element_key-Werte für $type, für die Validierung in UpdatePdfLayoutSettingsHandler */
    public static function allowedKeysFor(PdfDocumentType $type): array
    {
        return array_keys(self::elementsFor($type));
    }
}
