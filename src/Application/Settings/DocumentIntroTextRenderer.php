<?php

declare(strict_types=1);

namespace App\Application\Settings;

use App\Domain\Customer\Customer;
use App\Domain\Customer\CustomerContact;
use App\Domain\Customer\Salutation;
use App\Domain\Settings\DocumentIntroTextRepositoryInterface;

/**
 * Setzt die Platzhalter in Starttexten (und in den Einleitungen der
 * Angebots-/Rechnungsvorlage) ein (Migration 113):
 *
 *   {anrede}   "Sehr geehrter Herr Mustermann" — vom gewählten
 *              Ansprechpartner, sonst vom Kunden, sonst "Sehr geehrte Damen und Herren"
 *   {vorname}, {nachname}, {firma}, {nummer} (Dokumentnummer)
 */
final class DocumentIntroTextRenderer
{
    public const PLACEHOLDERS = ['{anrede}', '{vorname}', '{nachname}', '{firma}', '{nummer}'];

    public function __construct(private readonly ?DocumentIntroTextRepositoryInterface $texts = null)
    {
    }

    /** Fertiger Starttext (Klartext) für den Dokumenttyp, oder null. */
    public function render(string $documentType, ?Customer $customer, ?CustomerContact $contact, ?string $documentNumber = null): ?string
    {
        $template = $this->texts?->get($documentType);
        if ($template === null || trim($template) === '') {
            return null;
        }

        return strtr($template, $this->values($customer, $contact, $documentNumber));
    }

    /** Platzhalter in HTML ersetzen (Werte HTML-maskiert). */
    public function applyToHtml(?string $html, ?Customer $customer, ?CustomerContact $contact, ?string $documentNumber = null): ?string
    {
        if ($html === null || !str_contains($html, '{')) {
            return $html;
        }

        return strtr($html, array_map(static fn (string $value) => htmlspecialchars($value, ENT_QUOTES), $this->values($customer, $contact, $documentNumber)));
    }

    /** @return array<string, string> */
    public function values(?Customer $customer, ?CustomerContact $contact, ?string $documentNumber = null): array
    {
        if ($contact !== null && $contact->salutation !== null) {
            [$firstName, $lastName] = Salutation::splitName($contact->name);
            $salutation = $contact->salutation;
        } else {
            $firstName = $customer?->firstName ?? '';
            $lastName = $customer?->lastName ?? '';
            $salutation = $customer?->salutation;
            if ($contact !== null && $contact->salutation === null && $customer?->salutation === null) {
                [$firstName, $lastName] = Salutation::splitName($contact->name);
            }
        }

        return [
            '{anrede}' => Salutation::letterSalutation($salutation, $firstName, $lastName),
            '{vorname}' => $firstName,
            '{nachname}' => $lastName,
            '{firma}' => $customer?->companyName ?? '',
            '{nummer}' => $documentNumber ?? '',
        ];
    }
}
