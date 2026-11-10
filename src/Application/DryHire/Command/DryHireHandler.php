<?php

declare(strict_types=1);

namespace App\Application\DryHire\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\DryHire\DryHireRepositoryInterface;
use App\Domain\DryHire\DryHireTier;
use App\Domain\Exception\ValidationException;

/** Dry-Hire-Preise, -Kunden, -Staffel und Dokument-Kennzeichen (Migration 142). */
final class DryHireHandler implements CommandHandlerInterface
{
    public const MAX_TIERS = 20;

    public function __construct(private readonly DryHireRepositoryInterface $dryHire)
    {
    }

    public function handle(CommandInterface $command): mixed
    {
        match (true) {
            $command instanceof SetArticleDryHirePrice => $this->setArticlePrice($command),
            $command instanceof SetCustomerDryHire => $this->dryHire->setCustomerDryHire($command->customerId, $command->dryHire),
            $command instanceof SaveDryHireTiers => $this->saveTiers($command),
            $command instanceof SetDocumentDryHire => $this->dryHire->setDocumentFlag($command->documentType, $command->documentId, $command->enabled),
            default => throw new \LogicException('Unbekannter Befehl ' . $command::class),
        };

        return null;
    }

    /** Normalisiert „12,50“ → „12.50“; leer → null. */
    public static function normalizePrice(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        $value = str_replace([' ', '€'], '', $value);
        if (str_contains($value, ',')) {
            $value = str_replace(['.', ','], ['', '.'], $value);
        }
        if (!is_numeric($value) || (float) $value < 0 || (float) $value > 99999999) {
            throw new ValidationException(['dry_hire_daily_price' => 'Bitte einen gültigen Dry-Hire-Tagesmietsatz angeben.']);
        }

        return number_format((float) $value, 2, '.', '');
    }

    private function setArticlePrice(SetArticleDryHirePrice $command): void
    {
        $this->dryHire->setArticlePrice($command->articleId, self::normalizePrice($command->dailyPrice));
    }

    private function saveTiers(SaveDryHireTiers $command): void
    {
        $tiers = [];
        foreach ($command->tiers as [$day, $pct]) {
            $day = trim((string) $day);
            $pct = str_replace(',', '.', trim((string) $pct));
            if ($day === '' && $pct === '') {
                continue;
            }
            if (!ctype_digit($day) || (int) $day < 1 || (int) $day > 3650) {
                throw new ValidationException(['tiers' => '„Ab Tag“ muss eine Zahl zwischen 1 und 3650 sein.']);
            }
            if (!is_numeric($pct) || (float) $pct < 0 || (float) $pct > 1000) {
                throw new ValidationException(['tiers' => 'Der Prozentsatz muss zwischen 0 und 1000 liegen.']);
            }
            if (isset($tiers[(int) $day])) {
                throw new ValidationException(['tiers' => 'Tag ' . (int) $day . ' ist doppelt vergeben.']);
            }
            $tiers[(int) $day] = new DryHireTier((int) $day, number_format((float) $pct, 2, '.', ''));
        }
        if (!isset($tiers[1])) {
            throw new ValidationException(['tiers' => 'Die Staffel braucht eine Stufe ab Tag 1.']);
        }
        if (count($tiers) > self::MAX_TIERS) {
            throw new ValidationException(['tiers' => 'Höchstens ' . self::MAX_TIERS . ' Stufen.']);
        }
        ksort($tiers);
        $this->dryHire->saveTiers(array_values($tiers));
    }
}
