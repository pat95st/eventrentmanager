<?php

declare(strict_types=1);

namespace App\Application\Invoice\Command;

use App\Application\Common\VenueResolver;
use App\Domain\Article\ArticleRepositoryInterface;
use App\Domain\Customer\CustomerRepositoryInterface;
use App\Domain\LaborTime\LaborTimeArticleRepositoryInterface;
use App\Domain\Venue\VenueRepositoryInterface;
use DateTimeImmutable;

/**
 * Gemeinsame Validierung für Create-/UpdateInvoiceHandler, strukturell
 * identisch zu ValidatesOfferInput (Angebote) — der Kunde ist auch hier
 * bewusst OPTIONAL beim Anlegen/Bearbeiten (erst MarkInvoiceSentHandler
 * verlangt zwingend einen Kunden + mindestens eine Position, siehe dort).
 * Zusätzlich gegenüber Angeboten: buyerReference (BT-10, freies Feld, z. B.
 * Bestellnummer des Kunden — siehe XRechnungGenerator), keine
 * validUntil-Entsprechung.
 */
trait ValidatesInvoiceInput
{
    /**
     * @param array<int, array{article_id: string, quantity: string, unit_price: string, line_note: string}> $rawItems
     * @param array<int, array{labor_article_id: string, hours: string}> $rawLaborItems
     * @return array{0: array<string, string>, 1: array{
     *     customerId: ?string, startDate: ?DateTimeImmutable, endDate: ?DateTimeImmutable,
     *     eventLocation: ?string, venueId: ?string, buyerReference: ?string, note: ?string,
     *     items: array<int, array{articleId: string, quantity: int, unitPrice: string, lineNote: ?string}>,
     *     laborItems: array<int, array{laborTimeArticleId: string, hours: string, unitRatePerHour: string}>,
     *     discountType: ?string, discountValue: ?string,
     * }}
     */
    private function validateInvoiceInput(
        CustomerRepositoryInterface $customers,
        VenueRepositoryInterface $venues,
        ArticleRepositoryInterface $articles,
        LaborTimeArticleRepositoryInterface $laborTimeArticles,
        string $customerId,
        string $rawStartDate,
        string $rawEndDate,
        string $eventLocation,
        string $buyerReference,
        string $note,
        array $rawItems,
        array $rawLaborItems,
        ?string $rawDiscountType = null,
        ?string $rawDiscountValue = null,
    ): array {
        $errors = [];

        $customerId = trim($customerId);
        if ($customerId !== '' && $customers->findById($customerId) === null) {
            $errors['customerId'] = 'Der gewählte Kunde wurde nicht gefunden.';
        }

        [$resolvedLocation, $venueId] = VenueResolver::resolve($venues, $eventLocation);

        $startDate = $this->parseInvoiceDate($rawStartDate);
        if ($startDate === null) {
            $errors['startDate'] = 'Bitte ein gültiges Startdatum angeben.';
        }

        $endDate = $this->parseInvoiceDate($rawEndDate);
        if ($endDate === null) {
            $errors['endDate'] = 'Bitte ein gültiges Enddatum angeben.';
        }

        if ($startDate !== null && $endDate !== null && $endDate < $startDate) {
            $errors['endDate'] = 'Das Enddatum kann nicht vor dem Startdatum liegen.';
        }

        $note = trim($note);
        $buyerReference = trim($buyerReference);

        $items = [];
        foreach ($rawItems as $index => $rawItem) {
            $articleId = trim((string) ($rawItem['article_id'] ?? ''));
            if ($articleId === '') {
                continue;
            }

            if ($articles->findById($articleId) === null) {
                $errors['items'] = 'Mindestens eine Position enthält einen unbekannten Artikel.';

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
            ];
        }

        $laborItems = [];
        foreach ($rawLaborItems as $rawLaborItem) {
            $laborArticleId = trim((string) ($rawLaborItem['labor_article_id'] ?? ''));
            if ($laborArticleId === '') {
                continue;
            }

            $laborArticle = $laborTimeArticles->findById($laborArticleId);
            if ($laborArticle === null) {
                $errors['laborItems'] = 'Mindestens eine Arbeitszeit-Position enthält einen unbekannten Arbeitszeit-Artikel.';

                continue;
            }

            $rawHours = trim((string) ($rawLaborItem['hours'] ?? ''));
            $hours = filter_var(str_replace(',', '.', $rawHours), FILTER_VALIDATE_FLOAT);
            if ($rawHours === '' || $hours === false || $hours <= 0) {
                $errors['laborItems'] = 'Bitte für jede Arbeitszeit-Position eine gültige Stundenzahl größer als 0 angeben.';

                continue;
            }

            $laborItems[] = [
                'laborTimeArticleId' => $laborArticleId,
                'hours' => number_format($hours, 2, '.', ''),
                // Stundensatz wird beim Speichern gesnapshotet (siehe LaborTimeArticle-
                // Klassendoc), NICHT frei eingebbar — anders als unitPrice bei den
                // Mietpositionen.
                'unitRatePerHour' => $laborArticle->hourlyRate,
            ];
        }

        // Kombirabatt: entweder 'percent' oder 'fixed', mit positivem
        // numerischem Wert (bei 'percent' zusätzlich <= 100). Siehe
        // App\Domain\Pricing\DocumentTotals für die Anwendung.
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
            'buyerReference' => $buyerReference !== '' ? $buyerReference : null,
            'note' => $note !== '' ? $note : null,
            'items' => $items,
            'laborItems' => $laborItems,
            'discountType' => $discountType,
            'discountValue' => $discountValue,
        ]];
    }

    private function parseInvoiceDate(string $value): ?DateTimeImmutable
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
