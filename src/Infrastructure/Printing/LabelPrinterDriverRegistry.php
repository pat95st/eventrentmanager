<?php

declare(strict_types=1);

namespace App\Infrastructure\Printing;

use RuntimeException;

/**
 * Ordnet den in der Datenbank hinterlegten $driverType-String (siehe
 * App\Domain\Printing\LabelPrinter::$driverType) der passenden
 * Treiberimplementierung zu.
 *
 * Ein neues Druckermodell hinzuzufügen bedeutet ausschließlich:
 *   1. eine neue Klasse schreiben, die LabelPrinterDriverInterface implementiert
 *   2. hier einen weiteren Eintrag in self::DRIVER_CLASSES ergänzen
 *   3. in der Drucker-Formular-View (resources/views/settings/label-printers/index.php)
 *      eine weitere <option> für das neue Modell ergänzen
 * An Application-Handlern, Controllern oder der Datenbankstruktur ändert
 * sich dafür nichts.
 */
final class LabelPrinterDriverRegistry
{
    /** @var array<string, class-string<LabelPrinterDriverInterface>> */
    private const DRIVER_CLASSES = [
        'brother_ptouch_raster' => BrotherPtouchRasterDriver::class,
    ];

    /** @var array<string, LabelPrinterDriverInterface> Einmal erzeugte Treiberinstanzen (zustandslos, daher wiederverwendbar). */
    private array $instances = [];

    public function get(string $driverType): LabelPrinterDriverInterface
    {
        if (isset($this->instances[$driverType])) {
            return $this->instances[$driverType];
        }

        $class = self::DRIVER_CLASSES[$driverType] ?? null;
        if ($class === null) {
            throw new RuntimeException("Unbekannter Druckertreiber \"{$driverType}\".");
        }

        return $this->instances[$driverType] = new $class(new TcpPrinterTransport());
    }

    /** @return array<string, string> driver_type => Anzeigename, für das Auswahlfeld im Formular. */
    public static function availableDrivers(): array
    {
        return [
            'brother_ptouch_raster' => 'Brother P-touch (Raster)',
        ];
    }
}
