<?php

declare(strict_types=1);

namespace App\Infrastructure\Config;

use RuntimeException;

/**
 * Weder die über DATABASE_CONFIG_PATH angegebene noch die projektrelative
 * storage/database.yaml noch der alte feste Standardpfad existieren — die
 * Anwendung wurde offenbar noch nicht installiert. Eigene Exception-Klasse
 * (statt direkt RuntimeException), damit public/index.php diesen Fall gezielt
 * von anderen, unerwarteten Fehlern unterscheiden und auf den Web-Installer
 * (public/install.php) verweisen kann, siehe dort.
 */
final class NotInstalledException extends RuntimeException
{
    public function __construct(string $triedPath)
    {
        parent::__construct(
            "Keine Datenbank-Konfiguration gefunden (zuletzt versucht: \"{$triedPath}\"). "
            . 'Die Anwendung wurde vermutlich noch nicht installiert, siehe public/install.php.'
        );
    }
}
