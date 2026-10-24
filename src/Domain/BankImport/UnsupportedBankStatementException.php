<?php

declare(strict_types=1);

namespace App\Domain\BankImport;

use RuntimeException;

/**
 * Wird geworfen, wenn eine hochgeladene Datei weder als MT940 noch als
 * CAMT.053 erkannt werden kann, oder wenn das erkannte Format keine
 * auswertbaren Buchungen enthält.
 */
final class UnsupportedBankStatementException extends RuntimeException
{
}
