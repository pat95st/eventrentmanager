<?php

declare(strict_types=1);

namespace App\Application\TravelCost;

use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Domain\Invoice\InvoiceRepositoryInterface;
use App\Domain\Offer\OfferRepositoryInterface;
use App\Domain\OrderConfirmation\OrderConfirmationRepositoryInterface;
use App\Application\PlannedTrip\PlannedTripService;
use App\Domain\Rental\Rental;
use App\Domain\Rental\RentalRepositoryInterface;
use App\Domain\TravelCost\TravelCostBasis;
use App\Domain\TravelCost\TravelCostDocumentType;
use App\Domain\TravelCost\TravelCostLine;
use App\Domain\TravelCost\TravelCostMode;
use App\Domain\TravelCost\TravelCostSection;
use App\Domain\TravelCost\TravelCostSectionRepositoryInterface;
use App\Shared\Uuid;
use DateTimeImmutable;

/**
 * Travel cost section of offers, order confirmations and invoices
 * (migration 182): take it over from the linked event, switch the mode,
 * edit the lines and copy it along the document chain. Changes are only
 * possible while the document is a draft.
 *
 * Migration 187: the basis is either the planned kilometres (route of a
 * tour or planned trips of an event) or, for invoices only, the kilometres
 * actually driven according to the driver's logbook, or none at all.
 * Invoices preselect the driven kilometres as soon as billable logbook
 * trips exist.
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
        private readonly ?PlannedTripService $plannedTrips = null,
        private readonly ?ActualTravelCostCalculator $actual = null,
        private readonly ?\App\Domain\Vehicle\VehicleSettingsRepositoryInterface $vehicleSettings = null,
    ) {
    }

    /**
     * Header data of the document.
     *
     * @return array{number: string, editable: bool, tourRentalId: ?string, rentalId: ?string}
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
        $linkedRentalId = null;
        foreach ($rentalIds as $rentalId) {
            $rental = $rentalId !== null ? $this->rentals->findById($rentalId) : null;
            if ($rental === null) {
                continue;
            }
            $linkedRentalId ??= $rental->id;
            if ($rental->isTour) {
                $tourRentalId = $rental->id;
                $linkedRentalId = $rental->id;
                break;
            }
        }

        return ['number' => (string) $number, 'editable' => $editable, 'tourRentalId' => $tourRentalId, 'rentalId' => $linkedRentalId];
    }

    public function find(TravelCostDocumentType $type, string $documentId): ?TravelCostSection
    {
        return $this->sections->findFor($type, $documentId);
    }

    /** Takes the travel costs over from the linked tour (replaces an existing section). */
    public function importFromTour(TravelCostDocumentType $type, string $documentId): TravelCostSection
    {
        $section = $this->import($type, $documentId, 'planned');
        assert($section !== null);

        return $section;
    }

    /**
     * Choices of the travel cost panel of a document: planned and (invoices
     * only) driven kilometres of the linked event, and the preselected one.
     *
     * @return array{rental: ?Rental, enabled: bool, planned: ?TourTravelCostProposal, actual: ?TourTravelCostProposal, preselect: string, current: ?string}
     */
    public function choices(TravelCostDocumentType $type, string $documentId): array
    {
        $document = $this->document($type, $documentId);
        $rental = $document['rentalId'] !== null ? $this->rentals->findById($document['rentalId']) : null;
        $planned = $rental !== null ? $this->plannedProposal($rental) : null;
        $actual = $rental !== null && $type === TravelCostDocumentType::Invoice ? $this->actual?->forRental($rental) : null;
        $section = $this->sections->findFor($type, $documentId);
        $preselect = $actual !== null && $actual->trips !== [] ? 'actual' : ($planned !== null && $planned->trips !== [] ? 'planned' : 'none');

        return [
            'rental' => $rental,
            'enabled' => $rental?->travelCostsEnabled ?? false,
            'planned' => $planned,
            'actual' => $actual,
            'preselect' => $preselect,
            'current' => $section === null ? null : ($section->basis === TravelCostBasis::Actual ? 'actual' : 'planned'),
        ];
    }

    /**
     * Takes the travel costs over from the linked event: 'planned', 'actual'
     * (invoices only) or 'none' (removes the section). Replaces an existing
     * section.
     */
    public function import(TravelCostDocumentType $type, string $documentId, string $basis): ?TravelCostSection
    {
        $document = $this->editableDocument($type, $documentId);
        if ($basis === 'none') {
            $this->sections->deleteFor($type, $documentId);

            return null;
        }
        $rental = $document['rentalId'] !== null ? $this->rentals->findById($document['rentalId']) : null;
        if ($rental === null) {
            throw new ValidationException(['tour' => 'Das Dokument ist mit keiner Veranstaltung verknüpft.']);
        }
        if ($basis === 'actual' && $type !== TravelCostDocumentType::Invoice) {
            throw new ValidationException(['basis' => 'Die tatsächlich gefahrenen Kilometer können nur in Rechnungen abgerechnet werden.']);
        }
        $proposal = match ($basis) {
            'planned' => $this->plannedProposal($rental),
            'actual' => $this->actual?->forRental($rental),
            default => throw new ValidationException(['basis' => 'Bitte auswählen, welche Kilometer abgerechnet werden.']),
        };
        if ($proposal === null) {
            throw new ValidationException(['basis' => 'Für diese Veranstaltung gibt es keine geplanten Fahrten.']);
        }
        if (!$proposal->enabled) {
            throw new ValidationException(['tour' => 'Für die verknüpfte Veranstaltung ist „Fahrtkosten abrechnen“ ausgeschaltet.']);
        }
        if (!$proposal->isBillable()) {
            throw new ValidationException(['tour' => $proposal->hints !== [] ? implode(' ', $proposal->hints) : 'Es gibt keine abzurechnenden Fahrten.']);
        }
        $section = $this->withNote($proposal->toSection($type, $documentId));
        $this->sections->save($section);

        return $section;
    }

    /**
     * Takes the travel costs of the event over into a new invoice when it
     * bills them: the driven kilometres if the logbook has billable trips,
     * otherwise the planned ones; does nothing otherwise.
     */
    public function importForNewDocument(TravelCostDocumentType $type, string $documentId, string $rentalId): void
    {
        $rental = $this->rentals->findById($rentalId);
        if ($rental === null || !$rental->travelCostsEnabled) {
            return;
        }
        $proposal = $type === TravelCostDocumentType::Invoice ? $this->actual?->forRental($rental) : null;
        if ($proposal === null || !$proposal->isBillable()) {
            $proposal = $this->plannedProposal($rental);
        }
        if ($proposal !== null && $proposal->isBillable()) {
            $this->sections->save($this->withNote($proposal->toSection($type, $documentId)));
        }
    }

    /** The logbook trips of a section taken over from the driven kilometres have changed since. */
    public function logbookChanged(TravelCostSection $section): bool
    {
        if ($section->basis !== TravelCostBasis::Actual || $section->sourceRentalId === null || $this->actual === null) {
            return false;
        }
        $rental = $this->rentals->findById($section->sourceRentalId);

        return $rental !== null && $this->actual->fingerprintFor($rental) !== $section->sourceHash;
    }

    /** Prints the note below planned travel costs on offers and order confirmations, or not. */
    public function setEstimateNote(TravelCostDocumentType $type, string $documentId, bool $show): void
    {
        $this->editableDocument($type, $documentId);
        $section = $this->sections->findFor($type, $documentId) ?? throw new NotFoundException('Das Dokument hat keine Fahrtkosten.');
        $section->estimateNote = null;
        if ($show) {
            $section = $this->withNote($section);
        }
        $this->sections->save($section);
    }

    /** Copies the note of the vehicle settings into planned travel costs of offers and order confirmations. */
    private function withNote(TravelCostSection $section): TravelCostSection
    {
        $note = trim((string) $this->vehicleSettings?->get()->travelCostEstimateNote);
        if ($section->documentType !== TravelCostDocumentType::Invoice && $section->basis->isPlanned() && $note !== '') {
            $section->estimateNote = $note;
        }

        return $section;
    }

    /** Planned travel costs: route of a tour or planned trips of an event. */
    private function plannedProposal(Rental $rental): ?TourTravelCostProposal
    {
        if ($rental->isTour) {
            return $this->calculator->forRental($rental->id);
        }

        return $this->plannedTrips?->proposal($rental);
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

    /** @return array{number: string, editable: bool, tourRentalId: ?string, rentalId: ?string} */
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
