<?php

declare(strict_types=1);

namespace App\Domain\DigitalStagebox;

/**
 * Angaben einer digitalen Stagebox (Migration 152): digitales Protokoll,
 * analoge Eingänge (XLR- und/oder Kombibuchsen) und Ausgänge (analog
 * und/oder AES/EBU). Kombinationen sind ausdrücklich erlaubt.
 */
final class ArticleDigitalStagebox
{
    public const MAX_PER_TYPE = 999;

    public function __construct(
        public readonly string $articleId,
        public string $protocolId,
        public int $inputsXlr = 0,
        public int $inputsCombo = 0,
        public int $outputsAnalog = 0,
        public int $outputsAes = 0,
    ) {
    }

    public function totalInputs(): int
    {
        return $this->inputsXlr + $this->inputsCombo;
    }

    public function totalOutputs(): int
    {
        return $this->outputsAnalog + $this->outputsAes;
    }

    /** Eingänge für die Anzeige, z. B. "32 (24 × XLR, 8 × Kombi)" bzw. "16 × XLR". */
    public function inputsLabel(): string
    {
        return self::label($this->totalInputs(), ['XLR' => $this->inputsXlr, 'Kombi' => $this->inputsCombo]);
    }

    /** Ausgänge für die Anzeige, z. B. "16 (12 × analog, 4 × AES/EBU)". */
    public function outputsLabel(): string
    {
        return self::label($this->totalOutputs(), ['analog' => $this->outputsAnalog, 'AES/EBU' => $this->outputsAes]);
    }

    /** Kurzform "Eingänge/Ausgänge", z. B. "32/16". */
    public function channelSummary(): string
    {
        return $this->totalInputs() . '/' . $this->totalOutputs();
    }

    /** @param array<string, int> $parts */
    private static function label(int $total, array $parts): string
    {
        $used = array_filter($parts, static fn (int $count) => $count > 0);
        if ($used === []) {
            return 'keine';
        }
        $text = implode(', ', array_map(static fn (string $name, int $count) => $count . ' × ' . $name, array_keys($used), $used));

        return count($used) === 1 ? $text : $total . ' (' . $text . ')';
    }
}
