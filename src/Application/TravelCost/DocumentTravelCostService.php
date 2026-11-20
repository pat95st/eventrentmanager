<?php

declare(strict_types=1);

namespace App\Application\TravelCost;

use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Domain\Invoice\InvoiceRepositoryInterface;
use App\Domain\Offer\OfferRepositoryInterface;
use App\Domain\OrderConfirmation\OrderConfirmationRepositoryInterface;
use App\Domain\Rental\RentalRepositoryInterface;
use App\Domain\TravelCost\TravelCostDocumentType;
use App\Domain\TravelCost\TravelCostLine;
use App\Domain\TravelCost\TravelCostMode;
use App\Domain\TravelCost\TravelCostSection;
use App\Domain\TravelCost\TravelCostSectionRepositoryInterface;
use App\Shared\Uuid;
use DateTimeImmutable;

/**
 * Travel cost section of offers, order confirmations and invoices
 * (migration 182): take it over from the linked tour, switch the mode,
 * edit the lines and copy it along the document chain. Changes are only
 * possible while the document is a draft.
 */
final class DocumentTravelCostService
{
    public function __construct(
        private readonly TravelCostSectionRepositoryInterface $sections,
        private readonly TourTravelCostCalculator $calculator,
        private readonly OfferRepositoryInterface $offers,
        private readonly OrderConfirmationRepositoryInterface $orderConfirmations,
        private readonly InvoiceRepositoryInterface $invoices,
        private readonly RentalRepositoryInterface $rentals,
    ) {
    }

    /**
     * Header data of the document.
     *
     * @return array{number: string, editable: bool, tourRentalId: ?string}
     */
    public function document(TravelCostDocumentType $type, string $documentId): array
    {
        [$number, $editable, $rentalIds] = match ($type) {
            TravelCostDocumentType::Offer => (function () use ($documentId) {
                $offer = $this->offers->findById($documentId) ?? throw new NotFoundException('Das Angebot wurde nicht gefunden.');

                return [$offer->offerNumber, $offer->isEditable(), [$offer->sourceRentalId, $offer->convertedRentalId]];
            })(),
            TravelCostDocumentType::OrderConfirmation => (function () use ($documentId) {
                $confirmation = $this->orderConfirmations->findById($documentId) ?? throw new NotFoundException('Die Auftragsbestätigung wurde nicht gefunden.');

                return [$confirmation->orderConfirmationNumber, $confirmation->isEditable(), [$confirmation->sourceRentalId]];
            })(),
            TravelCostDocumentType::Invoice => (function () use ($documentId) {
                $invoice = $this->invoices->findById($documentId) ?? throw new NotFoundException('Die Rechnung wurde nicht gefunden.');

                return [$invoice->invoiceNumber ?? 'Entwurf', $invoice->isEditable(), [$invoice->sourceRentalId]];
            })(),
        };

        $tourRentalId = null;
        foreach ($rentalIds as $rentalId) {
            $rental = $rentalId !== null ? $this->rentals->findById($rentalId) : null;
            if ($rental !== null && $rental->isTour) {
                $tourRentalId = $rental->id;
                break;
            }
        }

        return ['number' => (string) $number, 'editable' => $editable, 'tourRentalId' => $tourRentalId];
    }

    public function find(TravelCostDocumentType $type, string $documentId): ?TravelCostSection
    {
        return $this->sections->findFor($type, $documentId);
    }

    /** Takes the travel costs over from the linked tour (replaces an existing section). */
    public function importFromTour(TravelCostDocumentType $type, string $documentId): TravelCostSection
    {
        $document = $this->editableDocument($type, $documentId);
        if ($document['tourRentalId'] === null) {
            throw new ValidationException(['tour' => 'Das Dokument ist mit keiner Tour verknüpft.']);
        }
        $proposal = $this->calculator->forRental($document['tourRentalId']);
        if (!$proposal->enabled) {
            throw new ValidationException(['tour' => 'Für die verknüpfte Tour ist „Fahrtkosten abrechnen“ ausgeschaltet.']);
        }
        if (!$proposal->isBillable()) {
            throw new ValidationException(['tour' => $proposal->hints !== [] ? implode(' ', $proposal->hints) : 'Die Tour hat keine abzurechnenden Fahrten.']);
        }
        $section = $proposal->toSection($type, $documentId);
        $this->sections->save($section);

        return $section;
    }

    /**
     * Takes the travel costs of a tour over into a new document when the tour
     * bills them (invoice from an event); does nothing otherwise.
     */
    public function importForNewDocument(TravelCostDocumentType $type, string $documentId, string $rentalId): void
    {
        $rental = $this->rentals->findById($rentalId);
        if ($rental === null || !$rental->isTour || !$rental->travelCostsEnabled) {
            return;
        }
        $proposal = $this->calculator->forRental($rentalId);
        if ($proposal->isBillable()) {
            $this->sections->save($proposal->toSection($type, $documentId));
        }
    }

    /** Copies the section of one document to another (e.g. invoice from an offer). */
    public function copy(TravelCostDocumentType $fromType, string $fromId, TravelCostDocumentType $toType, string $toId): void
    {
        $section = $this->sections->findFor($fromType, $fromId);
        if ($section !== null) {
            $this->sections->save($section->copyFor($toType, $toId));
        }
    }

    /** Switches between summary and single trips; the lines are rebuilt from the stored trips. */
    public function setMode(TravelCostDocumentType $type, string $documentId, string $mode): void
    {
        $this->editableDocument($type, $documentId);
        $section = $this->sections->findFor($type, $documentId) ?? throw new NotFoundException('Das Dokument hat keine Fahrtkosten.');
        $section->mode = TravelCostMode::tryFrom($mode) ?? throw new ValidationException(['mode' => 'Bitte eine Darstellung auswählen.']);
        $section->rebuildLines();
        $this->sections->save($section);
    }

    /**
     * Replaces the lines with the edited ones; rows with neither description
     * nor kilometres are ignored. Without lines the section is removed.
     *
     * @param list<array{date?: string, description?: string, detail?: string, km?: string, rate?: string}> $rows
     */
    public function saveLines(TravelCostDocumentType $type, string $documentId, array $rows): void
    {
        $this->editableDocument($type, $documentId);
        $section = $this->sections->findFor($type, $documentId) ?? throw new NotFoundException('Das Dokument hat keine Fahrtkosten.');

        $errors = [];
        $lines = [];
        foreach ($rows as $i => $row) {
            $description = trim((string) ($row['description'] ?? ''));
            $kmRaw = trim((string) ($row['km'] ?? ''));
            if ($description === '' && $kmRaw === '') {
                continue;
            }
            $n = $i + 1;
            if ($description === '') {
                $errors['lines.' . $i . '.description'] = 'Zeile ' . $n . ': Bitte eine Beschreibung angeben.';
            } elseif (mb_strlen($description) > 255) {
                $errors['lines.' . $i . '.description'] = 'Zeile ' . $n . ': Die Beschreibung ist zu lang (höchstens 255 Zeichen).';
            }
            $km = self::decimal($kmRaw);
            if ($km === null || $km < 0 || $km > 1000000) {
                $errors['lines.' . $i . '.km'] = 'Zeile ' . $n . ': Bitte die Kilometer als Zahl angeben.';
            }
            $rate = self::decimal(trim((string) ($row['rate'] ?? '')));
            if ($rate === null || $rate < 0 || $rate > 9999.99) {
                $errors['lines.' . $i . '.rate'] = 'Zeile ' . $n . ': Bitte den Preis pro km als Betrag angeben (z. B. 0,45).';
            }
            $date = null;
            $dateRaw = trim((string) ($row['date'] ?? ''));
            if ($dateRaw !== '') {
                $date = DateTimeImmutable::createFromFormat('!Y-m-d', $dateRaw) ?: null;
                if ($date === null) {
                    $errors['lines.' . $i . '.date'] = 'Zeile ' . $n . ': Bitte ein gültiges Datum angeben.';
                }
            }
            $detail = trim((string) ($row['detail'] ?? ''));
            if (mb_strlen($detail) > 1000) {
                $errors['lines.' . $i . '.detail'] = 'Zeile ' . $n . ': Der Zusatztext ist zu lang.';
            }
            if ($km !== null && $rate !== null) {
                $lines[] = new TravelCostLine(Uuid::v4(), $description, number_format($km, 2, '.', ''), number_format($rate, 2, '.', ''), $detail !== '' ? $detail : null, $date, count($lines));
            }
        }
        if ($errors !== []) {
            throw new ValidationException($errors);
        }
        if ($lines === []) {
            $this->sections->deleteFor($type, $documentId);

            return;
        }
        $section->lines = $lines;
        $this->sections->save($section);
    }

    public function remove(TravelCostDocumentType $type, string $documentId): void
    {
        $this->editableDocument($type, $documentId);
        $this->sections->deleteFor($type, $documentId);
    }

    /** @return array{number: string, editable: bool, tourRentalId: ?string} */
    private function editableDocument(TravelCostDocumentType $type, string $documentId): array
    {
        $document = $this->document($type, $documentId);
        if (!$document['editable']) {
            throw new ValidationException(['document' => 'Die Fahrtkosten können nur geändert werden, solange das Dokument ein Entwurf ist.']);
        }

        return $document;
    }

    /** "1.234,5" / "1234.5" / "0,45" → float, null if not a number. */
    private static function decimal(string $value): ?float
    {
        $value = str_replace(['€', 'km', ' '], '', $value);
        if ($value === '') {
            return null;
        }
        if (str_contains($value, ',')) {
            $value = str_replace(['.', ','], ['', '.'], $value);
        }

        return is_numeric($value) ? (float) $value : null;
    }
}
