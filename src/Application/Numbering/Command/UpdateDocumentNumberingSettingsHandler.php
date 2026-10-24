<?php

declare(strict_types=1);

namespace App\Application\Numbering\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Exception\ValidationException;
use App\Domain\Numbering\DocumentNumberFormatter;
use App\Domain\Numbering\DocumentNumberingSettings;
use App\Domain\Numbering\DocumentNumberingSettingsRepositoryInterface;
use App\Domain\Numbering\NumberedDocumentType;
use App\Domain\Numbering\NumberingScheme;

final class UpdateDocumentNumberingSettingsHandler implements CommandHandlerInterface
{
    public function __construct(private readonly DocumentNumberingSettingsRepositoryInterface $numberingSettings)
    {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof UpdateDocumentNumberingSettings);

        $errors = [];
        $toSave = [];
        $nextNumberOverrides = [];

        foreach (NumberedDocumentType::all() as $type) {
            $raw = $command->entries[$type->value] ?? null;
            if ($raw === null) {
                continue;
            }

            $fieldPrefix = $type->value . '.';

            $scheme = NumberingScheme::tryFrom((string) ($raw['scheme'] ?? ''));
            if ($scheme === null) {
                $errors[$fieldPrefix . 'scheme'] = 'Bitte ein gültiges Nummerierungsschema wählen.';
                continue;
            }

            $prefix = trim((string) ($raw['prefix'] ?? ''));
            $separator = (string) ($raw['separator'] ?? '-');
            if ($separator === '') {
                $separator = '-';
            }

            $digitCount = (int) ($raw['digit_count'] ?? 0);
            if ($digitCount < 1 || $digitCount > 10) {
                $errors[$fieldPrefix . 'digit_count'] = 'Die Anzahl der Ziffern muss zwischen 1 und 10 liegen.';
                continue;
            }

            $settings = new DocumentNumberingSettings(
                documentType: $type,
                scheme: $scheme,
                prefix: $prefix !== '' ? $prefix : null,
                separator: $separator,
                digitCount: $digitCount,
            );
            $toSave[] = $settings;

            $nextNumberRaw = trim((string) ($raw['next_number'] ?? ''));
            if ($nextNumberRaw !== '') {
                if (!ctype_digit($nextNumberRaw) || (int) $nextNumberRaw < 1) {
                    $errors[$fieldPrefix . 'next_number'] = 'Die nächste Nummer muss eine positive ganze Zahl sein.';
                    continue;
                }

                $year = DocumentNumberFormatter::previewYear($settings);
                $nextNumberOverrides[] = [$type, $year, (int) $nextNumberRaw];
            }
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        foreach ($toSave as $settings) {
            $this->numberingSettings->save($settings);
        }

        foreach ($nextNumberOverrides as [$type, $year, $next]) {
            $this->numberingSettings->setNextNumber($type, $year, $next);
        }

        return null;
    }
}
