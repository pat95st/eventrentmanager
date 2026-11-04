<?php

declare(strict_types=1);

namespace App\Application\PowerDistributor;

use App\Domain\PowerDistributor\ConnectorType;
use App\Domain\PowerDistributor\Outlet;
use App\Domain\PowerDistributor\PowerDistributor;
use App\Domain\PowerDistributor\ProtectiveDevice;
use App\Domain\PowerDistributor\ProtectiveDeviceType;
use App\Shared\Uuid;

/**
 * Prüft die Formularangaben eines Stromverteilers (Migration 117) und baut
 * daraus das Domänenobjekt, genutzt von Create-/UpdateArticleHandler
 * (Angaben direkt im Artikelformular).
 *
 * - Zuleitung Pflicht.
 * - RCD: Typ und Auslösestrom Pflicht (Nennstrom optional).
 * - LS: Charakteristik und Sicherungsgröße Pflicht; optional hinter einem RCD,
 *   auch als drei einzelne 1-polige LS (je Phase).
 * - FI/LS: alle vier Angaben Pflicht.
 * - Sicherung (Neozed/Diazed, Migration 129): System und Größe Pflicht, nur
 *   1-polig oder drei einzelne; optional hinter einem RCD.
 * - Einphasige Schutzorgane: Phase L1/L2/L3 optional.
 * - Abgänge: Steckverbindung, Anzahl 1–99, Zuordnung zu einem Schutzorgan
 *   oder direkt an der Zuleitung. CEE rot nicht an 1-/2-poliger
 *   Absicherung; einphasige Abgänge an dreiphasiger Absicherung brauchen
 *   eine Phase.
 * Bezeichnungen werden, wenn leer, automatisch vergeben (F1, F2, …).
 * Fehlerschlüssel: powerDistributorInlet, powerDistributorDevices, powerDistributorOutlets.
 */
final class PowerDistributorBuilder
{
    private const UUID_PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/';

    /** @return array{0: array<string, string>, 1: PowerDistributor|null} */
    public function build(string $articleId, PowerDistributorInput $input, ?PowerDistributor $existing): array
    {
        $errors = [];
        $deviceErrors = [];
        $outletErrors = [];
        $inlet = ConnectorType::tryFrom((string) $input->inletType);
        if ($inlet === null) {
            $errors['powerDistributorInlet'] = 'Bitte die Art der Zuleitung auswählen.';
        }

        $existingIds = [];
        foreach ($existing?->devices ?? [] as $device) {
            $existingIds[$device->id] = true;
        }

        // Schutzorgane: Schlüssel aus dem Formular => neue bzw. bestehende ID
        $devices = [];
        $idByKey = [];
        $rawUpstream = [];
        $usedLabels = [];
        $position = 0;
        foreach (array_values($input->devices) as $i => $raw) {
            $type = ProtectiveDeviceType::tryFrom((string) ($raw['type'] ?? ''));
            if ($type === null) {
                continue; // leere Zeile
            }
            $number = $i + 1;
            $key = trim((string) ($raw['key'] ?? ''));
            $id = ($key !== '' && isset($existingIds[$key]) && preg_match(self::UUID_PATTERN, $key) === 1) ? $key : Uuid::v4();
            if ($key !== '') {
                $idByKey[$key] = $id;
            }

            $label = mb_substr(trim((string) ($raw['label'] ?? '')), 0, 50);
            if ($label !== '' && isset($usedLabels[mb_strtolower($label)])) {
                $deviceErrors[] = "Die Bezeichnung „{$label}“ ist mehrfach vergeben.";
            }

            $poles = $this->intOrNull($raw['poles'] ?? null);
            if ($poles !== null && !in_array($poles, ProtectiveDevice::POLES, true)) {
                $deviceErrors[] = "Schutzorgan {$number}: ungültige Polzahl.";
            }

            $rcdType = null;
            $residual = null;
            if ($type->hasResidualProtection()) {
                $rcdType = (string) ($raw['rcd_type'] ?? '');
                $residual = $this->intOrNull($raw['residual_current_ma'] ?? null);
                if (!in_array($rcdType, ProtectiveDevice::RCD_TYPES, true)) {
                    $deviceErrors[] = "Schutzorgan {$number} ({$type->label()}): bitte den RCD-Typ auswählen.";
                }
                if (!in_array($residual, ProtectiveDevice::RESIDUAL_CURRENTS_MA, true)) {
                    $deviceErrors[] = "Schutzorgan {$number} ({$type->label()}): bitte den Auslösestrom auswählen.";
                }
            }

            // Drei einzelne 1-polige Geräte (je Phase) – nur bei LS und Sicherungen (Migration 129).
            $threeSingle = ($raw['three_single'] ?? '') === '1' && in_array($type, [ProtectiveDeviceType::Mcb, ProtectiveDeviceType::Fuse], true);
            $fuseSystem = null;
            if ($type === ProtectiveDeviceType::Fuse) {
                // Neozed/Diazed gibt es nur 1-polig – oder als drei einzelne für CEE rot.
                $poles = $threeSingle ? 3 : 1;
                $fuseSystem = (string) ($raw['fuse_system'] ?? '');
                if (!array_key_exists($fuseSystem, ProtectiveDevice::FUSE_SYSTEMS)) {
                    $deviceErrors[] = "Schutzorgan {$number}: bitte Neozed oder Diazed auswählen.";
                    $fuseSystem = null;
                }
            } elseif ($threeSingle) {
                $poles = 3;
            }

            $characteristic = null;
            $rated = $this->intOrNull($raw['rated_current_a'] ?? null);
            if ($type === ProtectiveDeviceType::Fuse) {
                if (!in_array($rated, ProtectiveDevice::FUSE_RATED_CURRENTS_A, true)) {
                    $deviceErrors[] = "Schutzorgan {$number} (Sicherung): bitte die Sicherungsgröße auswählen.";
                }
            } elseif ($type->hasOvercurrentProtection()) {
                $characteristic = (string) ($raw['characteristic'] ?? '');
                if (!in_array($characteristic, ProtectiveDevice::CHARACTERISTICS, true)) {
                    $deviceErrors[] = "Schutzorgan {$number} ({$type->label()}): bitte die Charakteristik auswählen.";
                }
                if (!in_array($rated, ProtectiveDevice::RATED_CURRENTS_A, true)) {
                    $deviceErrors[] = "Schutzorgan {$number} ({$type->label()}): bitte die Sicherungsgröße auswählen.";
                }
            } elseif ($rated !== null && !in_array($rated, ProtectiveDevice::RATED_CURRENTS_A, true)) {
                $deviceErrors[] = "Schutzorgan {$number}: ungültiger Nennstrom.";
            }

            $device = new ProtectiveDevice($id, $label, $type, $poles, $rcdType ?: null, $residual, $rated, $characteristic ?: null, null, $position++, $fuseSystem, $threeSingle);
            // Angeschlossene Phase nur bei einphasigen Schutzorganen.
            $phase = trim((string) ($raw['phase'] ?? ''));
            if ($phase !== '' && $device->isSinglePhase()) {
                if (in_array($phase, ProtectiveDevice::PHASES, true)) {
                    $device->phase = $phase;
                } else {
                    $deviceErrors[] = "Schutzorgan {$number}: ungültige Phase.";
                }
            }
            $devices[] = $device;
            if ($label !== '') {
                $usedLabels[mb_strtolower($label)] = true;
            }
            $rawUpstream[$id] = trim((string) ($raw['upstream_key'] ?? ''));
        }

        // Automatische Bezeichnungen F1, F2, … für leere Felder
        $counter = 1;
        foreach ($devices as $device) {
            if ($device->label === '') {
                while (isset($usedLabels[mb_strtolower('F' . $counter)])) {
                    $counter++;
                }
                $device->label = 'F' . $counter;
                $usedLabels[mb_strtolower($device->label)] = true;
            }
        }

        // Vorgeschaltete RCDs (nur bei LS)
        $byId = [];
        foreach ($devices as $device) {
            $byId[$device->id] = $device;
        }
        foreach ($devices as $device) {
            $upstreamKey = $rawUpstream[$device->id] ?? '';
            if ($upstreamKey === '') {
                continue;
            }
            $upstreamId = $idByKey[$upstreamKey] ?? null;
            $upstream = $upstreamId !== null ? ($byId[$upstreamId] ?? null) : null;
            if (!$device->type->canBeBehindRcd()) {
                $deviceErrors[] = "{$device->label}: Nur LS-Schalter und Sicherungen können hinter einem FI liegen.";
            } elseif ($upstream === null || $upstream->type !== ProtectiveDeviceType::Rcd) {
                $deviceErrors[] = "{$device->label}: Als vorgeschaltetes Schutzorgan kann nur ein RCD (FI) gewählt werden.";
            } else {
                $device->upstreamDeviceId = $upstream->id;
            }
        }

        $outlets = [];
        $position = 0;
        foreach (array_values($input->outlets) as $i => $raw) {
            $connector = ConnectorType::tryFrom((string) ($raw['connector_type'] ?? ''));
            if ($connector === null) {
                continue;
            }
            $number = $i + 1;
            $quantity = $this->intOrNull($raw['quantity'] ?? null) ?? 1;
            if ($quantity < 1 || $quantity > 99) {
                $outletErrors[] = "Abgang {$number}: Anzahl 1 bis 99.";
            }
            $deviceKey = trim((string) ($raw['device_key'] ?? ''));
            $deviceId = null;
            if ($deviceKey !== '') {
                $deviceId = $idByKey[$deviceKey] ?? null;
                if ($deviceId === null || !isset($byId[$deviceId])) {
                    $outletErrors[] = "Abgang {$number}: Das gewählte Schutzorgan gibt es nicht mehr.";
                    $deviceId = null;
                }
            }
            $label = mb_substr(trim((string) ($raw['label'] ?? '')), 0, 50);
            $device = $deviceId !== null ? $byId[$deviceId] : null;
            $phase = null;
            if ($connector->isThreePhase()) {
                // CEE rot braucht alle drei Phasen: keine einzelne 1-polige (oder 2-polige) Absicherung.
                if ($device !== null && $device->suppliesThreePhase() === false) {
                    $outletErrors[] = "Abgang {$number} ({$connector->shortLabel()}): braucht eine 3-/4-polige Absicherung oder 3 einzelne LS bzw. Sicherungen – {$device->label} ist " . ($device->poles ?? 1) . '-polig.';
                }
            } else {
                // Einphasiger Abgang an dreiphasiger Absicherung (oder direkt an CEE-rot-Zuleitung): Phase L1/L2/L3.
                $threePhaseSupply = $device !== null ? $device->suppliesThreePhase() === true : $inlet?->isThreePhase() === true;
                $rawPhase = trim((string) ($raw['phase'] ?? ''));
                if ($threePhaseSupply) {
                    if (in_array($rawPhase, ProtectiveDevice::PHASES, true)) {
                        $phase = $rawPhase;
                    } elseif ($device !== null) {
                        $outletErrors[] = "Abgang {$number} ({$connector->shortLabel()}): bitte die verwendete Phase (L1, L2 oder L3) wählen – {$device->label} ist dreiphasig.";
                    }
                }
            }
            $outlets[] = new Outlet(Uuid::v4(), $connector, $quantity, $label !== '' ? $label : null, $deviceId, $position++, $phase);
        }

        if ($deviceErrors !== []) {
            $errors['powerDistributorDevices'] = implode(' ', array_unique($deviceErrors));
        }
        if ($outletErrors !== []) {
            $errors['powerDistributorOutlets'] = implode(' ', array_unique($outletErrors));
        }
        if ($errors !== []) {
            return [$errors, null];
        }

        $notes = trim((string) $input->notes);

        return [[], new PowerDistributor($articleId, $inlet, $notes !== '' ? $notes : null, $devices, $outlets)];
    }

    private function intOrNull(mixed $value): ?int
    {
        $value = trim((string) $value);

        return $value !== '' && ctype_digit($value) ? (int) $value : null;
    }
}
