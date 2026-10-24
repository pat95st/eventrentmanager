<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Pdo;

use PDO;

final class Connection
{
    /**
     * @param array<string, string> $config Aus FlatYamlConfig::load() (database.yaml),
     *                                       erwartet die Schlüssel host/port/database/username/password/charset,
     *                                       optional zusätzlich ssl_ca (siehe buildOptions()).
     */
    public static function fromConfig(array $config): PDO
    {
        $host = $config['host'] ?? '127.0.0.1';
        $port = $config['port'] ?? '3306';
        $database = $config['database'] ?? 'materialverleih';
        $charset = $config['charset'] ?? 'utf8mb4';
        $user = $config['username'] ?? 'root';
        $password = $config['password'] ?? '';

        $dsn = "mysql:host={$host};port={$port};dbname={$database};charset={$charset}";

        return new PDO($dsn, $user, $password, self::buildOptions($config));
    }

    /**
     * Baut die PDO-Verbindungsoptionen auf — als eigene, ohne echte
     * Datenbankverbindung testbare Methode ausgelagert (siehe
     * Standalone-Tests). Ist im Config-Array der Schlüssel "ssl_ca" gesetzt
     * (Pfad zu einer CA-Zertifikatsdatei, z. B. vom Hosting-Anbieter einer
     * verwalteten MariaDB-Instanz bereitgestellt), wird die Verbindung
     * TLS-verschlüsselt aufgebaut und das Server-Zertifikat serverseitig
     * gegen diese CA geprüft (PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT) — das
     * lässt sich über den optionalen Schlüssel "ssl_verify_server_cert:
     * false" abschalten (z. B. bei einem selbstsignierten Zertifikat ohne
     * zum Hostnamen passendem CN/SAN). Fehlt "ssl_ca" (Normalfall), bleibt
     * die Verbindung wie bisher unverschlüsselt — reine Opt-in-Funktion,
     * ändert also nichts am Verhalten bestehender Installationen.
     *
     * @param array<string, string> $config
     * @return array<int, mixed>
     */
    public static function buildOptions(array $config): array
    {
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        $sslCa = trim((string) ($config['ssl_ca'] ?? ''));
        if ($sslCa !== '') {
            $options[PDO::MYSQL_ATTR_SSL_CA] = $sslCa;

            $verifyRaw = strtolower(trim((string) ($config['ssl_verify_server_cert'] ?? 'true')));
            $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = !in_array($verifyRaw, ['false', '0', 'nein', 'no'], true);
        }

        return $options;
    }
}
