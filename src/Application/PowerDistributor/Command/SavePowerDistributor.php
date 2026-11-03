<?php

declare(strict_types=1);

namespace App\Application\PowerDistributor\Command;

use App\Application\Command\CommandInterface;

/**
 * Stromverteiler-Angaben eines Artikels speichern (Migration 117).
 *
 * $devices: Zeilen mit key (vorhandene ID oder temporärer Schlüssel aus dem
 * Formular), label, type, poles, rcd_type, residual_current_ma,
 * rated_current_a, characteristic, upstream_key.
 * $outlets: Zeilen mit connector_type, quantity, label, device_key ('' = direkt).
 */
final class SavePowerDistributor implements CommandInterface
{
    /**
     * @param array<int, array<string, string>> $devices
     * @param array<int, array<string, string>> $outlets
     */
    public function __construct(
        public readonly string $articleId,
        public readonly ?string $inletType,
        public readonly ?string $notes,
        public readonly array $devices,
        public readonly array $outlets,
        // Elektroprüfung ist für Stromverteiler immer erforderlich; Schutzklasse dafür.
        public readonly ?string $protectionClass = null,
    ) {
    }
}
