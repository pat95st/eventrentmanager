<?php

declare(strict_types=1);

namespace App\Application\DigitalStagebox;

/** Stagebox-Angaben aus dem Artikelformular (Migration 152), Rohwerte als Strings. */
final class DigitalStageboxInput
{
    public function __construct(
        public readonly ?string $protocolId,
        public readonly ?string $inputsXlr,
        public readonly ?string $inputsCombo,
        public readonly ?string $outputsAnalog,
        public readonly ?string $outputsAes,
    ) {
    }
}
