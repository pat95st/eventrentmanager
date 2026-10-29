<?php

declare(strict_types=1);

namespace App\Application\OrderConfirmation\Command;

use App\Application\Common\VenueResolver;
use App\Domain\Article\ArticleRepositoryInterface;
use App\Domain\Customer\CustomerContactRepositoryInterface;
use App\Domain\Customer\CustomerRepositoryInterface;
use App\Domain\Rental\RentalRepositoryInterface;
use App\Domain\Venue\VenueRepositoryInterface;
use DateTimeImmutable;

/**
 * Gemeinsame Validierung für Create-/UpdateOrderConfirmationHandler, analog
 * ValidatesOfferInput — bewusst ohne Arbeitszeit-/Verkaufspositionen (siehe
 * OrderConfirmation-Klassen-Doc), dafür mit Freitext-Posten und
 * Zwischentexten (orderedRows()-Konzept).
 */
trait ValidatesOrderConfirmationInput
{
    /**
     * @param array<int, array{article_id: string, quantity: string, unit_price: string, line_note: string, sort_order?: string}> $rawItems
     * @param array<int, array{description: string, quantity: string, unit_price: string, sort_order?: string}> $rawFreeTextItems
     * @param array<int, array{heading: string, sort_order?: string}> $rawSectionTexts
     * @return array{0: array<string, string>, 1: array{
     *     customerId: ?string, startDate: ?DateTimeImmutable, endDate: ?DateTimeImmutable,
     *     eventLocation: ?string, venueId: ?string, note: ?string,
     *     items: array<int, array{articleId: string, quantity: int, unitPrice: string, lineNote: ?string, sortOrder: int}>,
     *     freeTextItems: array<int, array{description: string, quantity: string, unitPrice: string, sortOrder: int}>,
     *     sectionTexts: array<int, array{heading: string, sortOrder: int}>,
     *     discountType: ?string, discountValue: ?string,
     *     rentalId: ?string,
     *     contactId: ?string,
     * }}
     */
    private function validateOrderConfirmationInput(
        CustomerRepositoryInterface $customers,
        VenueRepositoryInterface $venues,
        ArticleRepositoryInterface $articles,
        string $customerId,
        string $rawStartDate,
        string $rawEndDate,
        string $eventLocation,
        string $note,
        array $rawItems,
        array $rawFreeTextItems,
        array $rawSectionTexts,
        ?string $rawDiscountType = null,
        ?string $rawDiscountValue = null,
        ?RentalRepositoryInterface $rentals = null,
        ?string $rawRentalId = null,
        ?CustomerContactRepositoryInterface $customerContacts = null,
        ?string $rawContactId = null,
    ): array {
        $errors = [];

        // Optionale Verknüpfung mit einer bestehenden Veranstaltung (Rental)
        // — siehe ValidatesInvoiceInput::validateInvoiceInput() für die
        // identische Begründung/Regel (bewusst dupliziert, gleiches Muster
        // wie der restliche Code dieser Traits).
        $rentalId = $rawRentalId !== null ? trim($rawRentalId) : '';
        if ($rentalId !== '' && $rentals !== null && $rentals->findById($rentalId) === null) {
            $errors['rentalId'] = 'Die gewählte Veranstaltung wurde nicht gefunden.';
            $rentalId = '';
        }

        // Ansprechpartner-Auswahl (Migration 104) — siehe
        // ValidatesInvoiceInput::validateInvoiceInput() für die identische
        // Begründung/Regel (bewusst dupliziert, gleiches Muster wie der
        // restliche Code dieser Traits).
        $contactId = $rawContactId !== null ? trim($rawContactId) : '';
        if ($contactId !== '' && $customerContacts !== null) {
            $contact = $customerContacts->findById($contactId);
            if ($contact === null || (trim($customerId) !== '' && $contact->customerId !== trim($customerId))) {
                $contactId = '';
            }
        }

        $customerId = trim($customerId);
        if ($customerId !== '' && $customers->findById($customerId) === null) {
            $errors['customerId'] = 'Der gewählte Kunde wurde nicht gefunden.';
        }

        [$resolvedLocation, $venueId] = VenueResolver::resolve($venues, $eventLocation);

        // Der Zeitraum ist optional (siehe Migration 102) — siehe
        // ValidatesOfferInput::validateOfferInput() für die identische
        // Begründung (eine Auftragsbestätigung kann auch unabhängig von
        // einer konkreten Veranstaltung erstellt werden).
        $rawStartDateTrimmed = trim($rawStartDate);
        $rawEndDateTrimmed = trim($rawEndDate);

        $startDate = null;
        if ($rawStartDateTrimmed !== '') {
            $startDate = $this->parseOrderConfirmationDate($rawStartDateTrimmed);
            if ($startDate === null) {
                $errors['startDate'] = 'Bitte ein gültiges Startdatum angeben.';
            }
        }

        $endDate = null;
        if ($rawEndDateTrimmed !== '') {
            $endDate = $this->parseOrderConfirmationDate($rawEndDateTrimmed);
            if ($endDate === null) {
                $errors['endDate'] = 'Bitte ein gültiges Enddatum angeben.';
            }
        }

        if (($rawStartDateTrimmed === '') !== ($rawEndDateTrimmed === '')) {
            $errors['endDate'] = 'Bitte entweder Start- und Enddatum beide angeben oder beide leer lassen.';
        }

        if ($startDate !== null && $endDate !== null && $endDate < $startDate) {
            $errors['endDate'] = 'Das Enddatum kann nicht vor dem Startdatum liegen.';
        }

        $note = trim($note);

        $items = [];
        foreach ($rawItems as $index => $rawItem) {
            $articleId = trim((string) ($rawItem['article_id'] ?? ''));
            if ($articleId === '') {
                continue;
            }

            $itemArticle = $articles->findById($articleId);
            if ($itemArticle === null) {
                $errors['items'] = 'Mindestens eine Position enthält einen unbekannten Artikel.';

                continue;
            }

            // Bundles (Migration 107): nicht einzeln vermietbare Artikel
            // dürfen nur über ihr Bundle in Miet-Positionen auftauchen.
            if (!$itemArticle->isRentableIndividually) {
                $errors['items'] = 'Mindestens eine Position enthält einen Artikel, der nicht einzeln vermietbar ist (nur als Bestandteil eines Bundles) — bitte stattdessen das passende Bundle wählen.';

                continue;
            }

            $quantity = filter_var(trim((string) ($rawItem['quantity'] ?? '')), FILTER_VALIDATE_INT);
            if ($quantity === false || $quantity < 1) {
                $errors['items'] = 'Bitte für jede Position eine Menge von mindestens 1 angeben.';

                continue;
            }

            $rawUnitPrice = trim((string) ($rawItem['unit_price'] ?? ''));
            $unitPrice = filter_var(str_replace(',', '.', $rawUnitPrice), FILTER_VALIDATE_FLOAT);
            if ($rawUnitPrice === '' || $unitPrice === false || $unitPrice < 0) {
                $errors['items'] = 'Bitte für jede Position einen gültigen Preis (0 oder größer) angeben.';

                continue;
            }

            $lineNote = trim((string) ($rawItem['line_note'] ?? ''));

            $items[] = [
                'articleId' => $articleId,
                'quantity' => $quantity,
                'unitPrice' => number_format($unitPrice, 2, '.', ''),
                'lineNote' => $lineNote !== '' ? $lineNote : null,
                'sortOrder' => (int) ($rawItem['sort_order'] ?? $index),
            ];
        }

        $freeTextItems = [];
        foreach ($rawFreeTextItems as $index => $rawFreeTextItem) {
            $description = trim((string) ($rawFreeTextItem['description'] ?? ''));
            if ($description === '') {
                continue;
            }

            $rawQuantity = trim((string) ($rawFreeTextItem['quantity'] ?? '1'));
            $quantity = filter_var(str_replace(',', '.', $rawQuantity), FILTER_VALIDATE_FLOAT);
            if ($rawQuantity === '' || $quantity === false) {
                $errors['freeTextItems'] = 'Bitte für jeden Freitext-Posten eine gültige Menge angeben.';

                continue;
            }

            $rawUnitPrice = trim((string) ($rawFreeTextItem['unit_price'] ?? '0'));
            $unitPrice = filter_var(str_replace(',', '.', $rawUnitPrice), FILTER_VALIDATE_FLOAT);
            if ($unitPrice === false) {
                $errors['freeTextItems'] = 'Bitte für jeden Freitext-Posten einen gültigen Preis angeben.';

                continue;
            }

            $freeTextItems[] = [
                'description' => $description,
                'quantity' => number_format($quantity, 2, '.', ''),
                'unitPrice' => number_format($unitPrice, 2, '.', ''),
                'sortOrder' => (int) ($rawFreeTextItem['sort_order'] ?? (1000 + $index)),
            ];
        }

        $sectionTexts = [];
        foreach ($rawSectionTexts as $index => $rawSectionText) {
            $heading = trim((string) ($rawSectionText['heading'] ?? ''));
            if ($heading === '') {
                continue;
            }

            $sectionTexts[] = [
                'heading' => $heading,
                'sortOrder' => (int) ($rawSectionText['sort_order'] ?? (2000 + $index)),
            ];
        }

        $discountType = $rawDiscountType !== null && trim($rawDiscountType) !== '' ? trim($rawDiscountType) : null;
        $discountValue = null;
        if ($discountType !== null) {
            if (!in_array($discountType, ['percent', 'fixed'], true)) {
                $errors['discountType'] = 'Bitte einen gültigen Rabatt-Typ wählen.';
            } else {
                $rawValue = str_replace(',', '.', trim((string) $rawDiscountValue));
                $value = filter_var($rawValue, FILTER_VALIDATE_FLOAT);
                if ($rawValue === '' || $value === false || $value <= 0) {
                    $errors['discountValue'] = 'Bitte einen gültigen, positiven Rabattwert angeben.';
                } elseif ($discountType === 'percent' && $value > 100) {
                    $errors['discountValue'] = 'Ein prozentualer Rabatt kann nicht größer als 100 % sein.';
                } else {
                    $discountValue = number_format($value, 2, '.', '');
                }
            }
        }

        return [$errors, [
            'customerId' => $customerId !== '' ? $customerId : null,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'eventLocation' => $resolvedLocation,
            'venueId' => $venueId,
            'note' => $note !== '' ? $note : null,
            'items' => $items,
            'freeTextItems' => $freeTextItems,
            'sectionTexts' => $sectionTexts,
            'discountType' => $discountType,
            'discountValue' => $discountValue,
            'rentalId' => $rentalId !== '' ? $rentalId : null,
            'contactId' => $contactId !== '' ? $contactId : null,
        ]];
    }

    private function parseOrderConfirmationDate(string $value): ?DateTimeImmutable
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        try {
            return new DateTimeImmutable($value);
        } catch (\Exception) {
            return null;
        }
    }
}
