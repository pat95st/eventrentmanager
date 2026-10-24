<?php

declare(strict_types=1);

namespace App\Application\Printing\Command;

use App\Infrastructure\Printing\LabelPrinterDriverRegistry;

/** Gemeinsame Validierung für Create-/UpdateLabelPrinterHandler. */
trait ValidatesLabelPrinterInput
{
    /**
     * @return array{0: array<string, string>, 1: array{
     *     name: string, driverType: string, ipAddress: string, port: int, tapeWidthMm: int
     * }}
     */
    private function validateLabelPrinterInput(
        string $name,
        string $driverType,
        string $ipAddress,
        string $port,
        string $tapeWidthMm,
    ): array {
        $errors = [];

        $name = trim($name);
        if ($name === '') {
            $errors['name'] = 'Bitte einen Namen angeben.';
        }

        $driverType = trim($driverType);
        if (!array_key_exists($driverType, LabelPrinterDriverRegistry::availableDrivers())) {
            $errors['driverType'] = 'Bitte einen gültigen Druckertyp wählen.';
        }

        $ipAddress = trim($ipAddress);
        if ($ipAddress === '' || filter_var($ipAddress, FILTER_VALIDATE_IP) === false) {
            $errors['ipAddress'] = 'Bitte eine gültige IP-Adresse angeben.';
        }

        $portValue = (int) $port;
        if ($port === '' || $portValue < 1 || $portValue > 65535) {
            $errors['port'] = 'Bitte einen Port zwischen 1 und 65535 angeben.';
        }

        $tapeWidthValue = (int) $tapeWidthMm;
        if ($tapeWidthMm === '' || $tapeWidthValue < 1) {
            $errors['tapeWidthMm'] = 'Bitte eine Bandbreite wählen.';
        }

        return [$errors, [
            'name' => $name,
            'driverType' => $driverType,
            'ipAddress' => $ipAddress,
            'port' => $portValue,
            'tapeWidthMm' => $tapeWidthValue,
        ]];
    }
}
