<?php

declare(strict_types=1);

/**
 * Web-Installer, analog zum bekannten WordPress-Installationsassistenten:
 * führt beim allerersten Einrichten der Anwendung durch (1) eine
 * Systemvoraussetzungs-Prüfung, (2) die Eingabe/Prüfung der
 * Datenbank-Zugangsdaten samt automatischem Einspielen aller Migrationen
 * aus database/migrations/ (siehe App\Infrastructure\Migrations\MigrationRunner),
 * (3) das Festlegen eines eigenen Administrator-Zugangs (ersetzt den sonst
 * per Migration 008 fest vergebenen Standard-Zugang) und (4) das
 * automatische Schreiben der database.yaml.
 *
 * WICHTIG: Diese Datei darf NICHT von src/bootstrap.php abhängen — bootstrap.php
 * verlangt zwingend eine bereits funktionierende database.yaml samt
 * erreichbarer Datenbank (siehe dortige Kommentare), was bei einer frischen
 * Installation per Definition noch nicht gegeben ist. Es wird lediglich der
 * schlanke Klassen-Autoloader (src/autoload.php) genutzt.
 *
 * Nach erfolgreichem Abschluss wird eine Sperrdatei (storage/installed.lock)
 * angelegt: ein erneuter Aufruf dieser Seite zeigt danach nur noch einen
 * Hinweis, dass die Installation bereits abgeschlossen ist, und führt keine
 * Aktion mehr aus (auch keine erneute Verarbeitung von POST-Daten) — das
 * verhindert, dass diese Seite nach der Installation als Werkzeug zum
 * Zurücksetzen des Administrator-Zugangs durch einen anonymen Website-Besucher
 * missbraucht werden kann. Zum bewussten Zurücksetzen (z. B. vergessenes
 * Administrator-Passwort) kann ein Administrator mit Dateizugriff auf den
 * Server storage/installed.lock manuell löschen; die Migrationen werden
 * dank schema_migrations-Tabelle beim erneuten Durchlauf idempotent nur für
 * noch fehlende Dateien wiederholt.
 */

require_once __DIR__ . '/../src/autoload.php';

use App\Infrastructure\Backup\BackupArchiveException;
use App\Infrastructure\Backup\BackupArchiveImporter;
use App\Infrastructure\Config\FlatYamlConfig;
use App\Infrastructure\Http\Csrf;
use App\Infrastructure\Migrations\MigrationFailedException;
use App\Infrastructure\Migrations\MigrationRunner;
use App\Infrastructure\Persistence\Pdo\Connection;
use App\Infrastructure\Persistence\Pdo\MariadbDatabaseRestorer;
use App\Shared\PasswordPolicy;
use App\Shared\Uuid;

const PROJECT_ROOT = __DIR__ . '/..';
const LOCK_FILE = PROJECT_ROOT . '/storage/installed.lock';
const CONFIG_FILE = PROJECT_ROOT . '/storage/database.yaml';
const MIGRATIONS_DIR = PROJECT_ROOT . '/database/migrations';
const SCHEMA_SQL_FILE = PROJECT_ROOT . '/database/schema.sql';
const UPLOADS_DIR = PROJECT_ROOT . '/storage/uploads';
const TMP_DIR = PROJECT_ROOT . '/storage/tmp';
// Dauerhafter Ablageort einer optional hochgeladenen SSL-CA-Zertifikatsdatei
// (siehe handleSslCaUpload()) — liegt bewusst neben database.yaml unter
// storage/ (außerhalb des öffentlichen Webroots public/), fester Dateiname,
// wird bei jedem erneuten Upload überschrieben.
const SSL_CA_FILE = PROJECT_ROOT . '/storage/database-ssl-ca.pem';

/**
 * Eine Datenbank gilt als "wirklich leer" (echte Neuanlage), wenn sie noch
 * keine einzige Tabelle enthält — das ist der zuverlässigste Indikator,
 * unabhängig vom konkreten Tabellennamen.
 */
function databaseIsEmpty(\PDO $pdo): bool
{
    $stmt = $pdo->query('SHOW TABLES');

    return $stmt !== false && $stmt->fetchAll() === [];
}

/**
 * Richtet eine wirklich leere Datenbank in EINEM Rutsch über database/schema.sql
 * ein, statt alle ~90 Migrationsdateien einzeln nacheinander per MigrationRunner
 * abzuspielen.
 *
 * Grund: database/migrations/ enthält ausschließlich INKREMENTELLE
 * ALTER-TABLE-Migrationen für bereits bestehende Installationen (z. B. fügt
 * 001_add_prefixes.sql eine Spalte zu einer bereits vorhandenen
 * "manufacturers"-Tabelle hinzu) — die ursprünglichen CREATE-TABLE-
 * Anweisungen für die Basistabellen selbst sind NICHT Teil der
 * Migrationshistorie, sondern nur in schema.sql enthalten. Auf einer
 * wirklich leeren Datenbank würde bereits die allererste Migration mit
 * "Table ... doesn't exist" fehlschlagen.
 *
 * database/schema.sql wird deshalb als vollständiger, aktueller
 * Schema-Snapshot gepflegt (zuletzt abgeglichen gegen alle Migrationen bis
 * einschließlich 088_add_ai_ad_images.sql) und hier direkt eingespielt.
 * Damit ein anschließendes "php bin/migrate.php" (oder ein erneuter
 * Installationslauf) die soeben eingespielten Migrationen nicht fälschlich
 * ein zweites Mal anwendet (würde z. B. mit "Duplicate column" fehlschlagen),
 * werden alle zum Zeitpunkt dieses Laufs vorhandenen Migrationsdateien direkt
 * im Anschluss als bereits angewendet in schema_migrations vermerkt.
 *
 * @return array<int, array{file: string, status: string}> im selben Format
 *         wie MigrationRunner::applyAll(), zur einheitlichen Anzeige in
 *         Schritt 2 des Assistenten.
 */
function applyFreshSchema(\PDO $pdo): array
{
    $schemaSql = file_get_contents(SCHEMA_SQL_FILE);
    if ($schemaSql === false) {
        throw new \RuntimeException('database/schema.sql konnte nicht gelesen werden.');
    }

    // "CREATE DATABASE ...;" und "USE ...;" entfernen: die PDO-Verbindung
    // zeigt über Connection::fromConfig() bereits auf die vom Administrator
    // im Formular angegebene Datenbank, deren Name vom hier hartkodierten
    // "materialverleih" abweichen kann (z. B. eine Testdatenbank wie
    // "materialtest") — ein erneutes CREATE DATABASE/USE würde entweder an
    // fehlenden Rechten scheitern (typisch bei Hosting-Angeboten) oder
    // versehentlich in die falsche Datenbank schreiben. Exakt dasselbe
    // Vorgehen wie bereits in MigrationRunner::applyOne() für "USE"-Zeilen
    // in einzelnen Migrationsdateien.
    $schemaSql = (string) preg_replace('/CREATE\s+DATABASE.*?;/si', '', $schemaSql);
    $schemaSql = (string) preg_replace('/^\s*USE\s+`?\w+`?\s*;\s*$/mi', '', $schemaSql);

    // PDO_MySQL führt bei exec() mehrere, durch Semikolon getrennte
    // Anweisungen in einem Aufruf aus (siehe identischer Kommentar in
    // MigrationRunner::applyOne()).
    $pdo->exec($schemaSql);

    $runner = new MigrationRunner($pdo, MIGRATIONS_DIR);
    // Legt bei Bedarf die schema_migrations-Tabelle an (siehe
    // MigrationRunner::ensureMigrationsTable(), privat, daher indirekt über
    // einen beliebigen öffentlichen Methodenaufruf angestoßen).
    $runner->appliedMigrations();

    $appliedAt = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
    $insert = $pdo->prepare('INSERT INTO schema_migrations (migration, applied_at) VALUES (:migration, :applied_at)');

    $log = [];
    foreach ($runner->migrationFiles() as $file) {
        $basename = basename($file);
        $insert->execute(['migration' => $basename, 'applied_at' => $appliedAt]);
        $log[] = ['file' => $basename, 'status' => 'applied'];
    }

    return $log;
}

function h(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Bereits abgeschlossene Installationen brechen hier sofort ab, VOR dem
 * Start der Session und VOR jeder Verarbeitung von POST-Daten — siehe
 * Begründung oben im Datei-Kommentar.
 */
if (is_file(LOCK_FILE)) {
    http_response_code(200);
    ?>
    <!DOCTYPE html>
    <html lang="de">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Bereits installiert – Materialverleih Software</title>
        <?php require __DIR__ . '/../resources/views/_install_style.php'; ?>
    </head>
    <body>
        <div class="install-shell">
            <div class="install-card">
                <h1>Bereits installiert</h1>
                <p>Diese Anwendung wurde bereits eingerichtet. Der Installer führt deshalb keine weiteren Aktionen mehr aus.</p>
                <p class="install-hint">
                    Soll die Installation (z. B. zum Zurücksetzen des Administrator-Zugangs) erneut durchlaufen werden,
                    muss ein Administrator mit Dateizugriff auf dem Server die Datei <code>storage/installed.lock</code> löschen.
                </p>
                <a class="install-btn" href="/login">Zur Anmeldung</a>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

session_start();

if (!isset($_SESSION['installer']) || !is_array($_SESSION['installer'])) {
    $_SESSION['installer'] = [
        'step' => 1,
        'db' => null,
        'migration_log' => null,
        // Gesetzt, nachdem in Schritt 2 statt einer frischen Einrichtung
        // eine Sicherung wiederhergestellt wurde (siehe 'test_and_restore'
        // unten) — steuert, welcher Inhalt in Schritt 3 angezeigt wird.
        'restored' => false,
        'restore_summary' => null,
        // null = noch nicht entschieden, true = zusätzlichen Administrator
        // überspringen (Schritt 3 entfällt), false = trotzdem anlegen.
        'skip_admin' => null,
    ];
}
$state = &$_SESSION['installer'];

$error = null;
$formValues = [];

// --- Systemvoraussetzungen (Schritt 1) ----------------------------------

function checkRequirements(): array
{
    $checks = [];

    $phpOk = version_compare(PHP_VERSION, '8.1.0', '>=');
    $checks[] = ['label' => 'PHP-Version ≥ 8.1 (aktuell: ' . PHP_VERSION . ')', 'ok' => $phpOk, 'blocking' => true];

    $checks[] = ['label' => 'PHP-Erweiterung pdo_mysql', 'ok' => extension_loaded('pdo_mysql'), 'blocking' => true];
    $checks[] = ['label' => 'PHP-Erweiterung fileinfo', 'ok' => extension_loaded('fileinfo'), 'blocking' => true];

    // storage/ selbst fehlt bei einem wirklich frischen Deployment oft komplett
    // (Git versioniert keine leeren Verzeichnisse, und ein Upload per ZIP/FTP
    // lässt leere Ordner gerne weg) — statt den Assistenten hier bereits mit
    // einem Fehler zu blockieren, den der Besucher ohne Server-Dateizugriff gar
    // nicht beheben könnte, legen wir storage/ (und die beiden unten direkt
    // benötigten Unterordner) bei Bedarf selbst an. storage/uploads/ würde
    // zwar auch später von LocalFileStorage lazy angelegt, storage/tmp/ wird
    // aber schon in Schritt 2 für eine optionale Sicherungs-Wiederherstellung
    // gebraucht — beide schon hier anzulegen hält diese eine Prüfung
    // vollständig aussagekräftig für ALLES, was der Installer schreiben muss.
    $storageDir = PROJECT_ROOT . '/storage';
    if (!is_dir($storageDir)) {
        @mkdir($storageDir, 0775, true);
    }
    if (is_dir($storageDir)) {
        foreach ([UPLOADS_DIR, TMP_DIR] as $subDir) {
            if (!is_dir($subDir)) {
                @mkdir($subDir, 0775, true);
            }
        }
    }
    $checks[] = ['label' => 'Schreibrecht in storage/ (für database.yaml und Uploads)', 'ok' => is_dir($storageDir) && is_writable($storageDir), 'blocking' => true];

    $checks[] = ['label' => 'PHP-Erweiterung openssl (für WebAuthn/Push-Benachrichtigungen empfohlen)', 'ok' => extension_loaded('openssl'), 'blocking' => false];
    $checks[] = ['label' => 'PHP-Erweiterung gd (für Firmenlogo in PDFs empfohlen)', 'ok' => extension_loaded('gd'), 'blocking' => false];

    return $checks;
}

$requirements = checkRequirements();
$requirementsOk = !in_array(false, array_map(fn (array $c) => !$c['blocking'] || $c['ok'], $requirements), true);

// --- POST-Verarbeitung ----------------------------------------------------

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $csrfOk = Csrf::verify((string) ($_POST['csrf_token'] ?? ''));

    if (!$csrfOk) {
        $error = 'Sicherheitsprüfung fehlgeschlagen (Sitzung abgelaufen?). Bitte die Seite neu laden und erneut versuchen.';
    } elseif ($action === 'continue_to_db' && $state['step'] === 1) {
        if ($requirementsOk) {
            $state['step'] = 2;
        } else {
            $error = 'Es sind noch nicht alle notwendigen Systemvoraussetzungen erfüllt.';
        }
    } elseif ($action === 'test_and_migrate' && $state['step'] <= 2) {
        $formValues = [
            'host' => trim((string) ($_POST['host'] ?? '')),
            'port' => trim((string) ($_POST['port'] ?? '3306')),
            'database' => trim((string) ($_POST['database'] ?? '')),
            'username' => trim((string) ($_POST['username'] ?? '')),
            'password' => (string) ($_POST['password'] ?? ''),
            'charset' => trim((string) ($_POST['charset'] ?? 'utf8mb4')),
        ];

        if ($formValues['host'] === '' || $formValues['database'] === '' || $formValues['username'] === '') {
            $error = 'Bitte Host, Datenbankname und Benutzername angeben.';
        } else {
            try {
                $sslCaPath = handleSslCaUpload();
                if ($sslCaPath !== null) {
                    $formValues['ssl_ca'] = $sslCaPath;
                }

                $pdo = Connection::fromConfig($formValues);

                // Eine wirklich leere Datenbank (Normalfall bei einer frischen
                // Installation) bekommt ihr Schema in einem Rutsch aus
                // database/schema.sql statt über den inkrementellen
                // MigrationRunner — siehe applyFreshSchema()-Klassendoc für die
                // Begründung (die Migrationsdateien allein setzen bereits
                // bestehende Basistabellen voraus). Bei einer bereits
                // (teilweise) bestückten Datenbank bleibt der bisherige,
                // inkrementelle Weg unverändert — wichtig für das Nachziehen
                // neuer Migrationen bei einer bestehenden Installation nach
                // einem Update.
                if (databaseIsEmpty($pdo)) {
                    $log = applyFreshSchema($pdo);
                } else {
                    $runner = new MigrationRunner($pdo, MIGRATIONS_DIR);
                    $log = $runner->applyAll();
                }

                $state['db'] = $formValues;
                $state['migration_log'] = $log;
                $state['restored'] = false;
                $state['restore_summary'] = null;
                $state['skip_admin'] = null;
                $state['step'] = 3;
                $formValues = [];
            } catch (\PDOException $e) {
                $error = 'Verbindung zur Datenbank fehlgeschlagen: ' . $e->getMessage();
            } catch (MigrationFailedException $e) {
                $state['migration_log'] = $e->log;
                $error = $e->getMessage() . ' Nach dem Beheben der Ursache (z. B. fehlende Berechtigung) kann dieser Schritt '
                    . 'einfach erneut ausgeführt werden – bereits erfolgreich eingespielte Migrationen werden dabei automatisch übersprungen.';
            } catch (\RuntimeException $e) {
                $error = $e->getMessage();
            }
        }
    } elseif ($action === 'test_and_restore' && $state['step'] <= 2) {
        // Alternative zu 'test_and_migrate': statt einer frischen Einrichtung
        // wird eine zuvor über die Admin-Oberfläche (BackupSettingsController)
        // oder einen früheren Installationslauf exportierte Sicherungs-ZIP
        // komplett eingespielt (siehe BackupArchiveImporter) — ERSETZT dabei
        // alle Tabelleninhalte der angegebenen Datenbank sowie den kompletten
        // Inhalt von storage/uploads/. Auf einer wirklich leeren Datenbank
        // (Normalfall bei einer frischen Installation) ist das unkritisch;
        // wird hier eine bereits befüllte Datenbank angegeben, ist das
        // beabsichtigt (z. B. Wiederherstellung nach einem Server-Umzug).
        $formValues = [
            'host' => trim((string) ($_POST['host'] ?? '')),
            'port' => trim((string) ($_POST['port'] ?? '3306')),
            'database' => trim((string) ($_POST['database'] ?? '')),
            'username' => trim((string) ($_POST['username'] ?? '')),
            'password' => (string) ($_POST['password'] ?? ''),
            'charset' => trim((string) ($_POST['charset'] ?? 'utf8mb4')),
        ];

        $uploadedZip = $_FILES['backup_zip'] ?? null;

        if ($formValues['host'] === '' || $formValues['database'] === '' || $formValues['username'] === '') {
            $error = 'Bitte Host, Datenbankname und Benutzername angeben.';
        } elseif (!is_array($uploadedZip) || ($uploadedZip['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            $error = 'Bitte eine Sicherungs-ZIP-Datei auswählen.';
        } elseif ($uploadedZip['error'] !== UPLOAD_ERR_OK) {
            $error = 'Die Sicherungsdatei konnte nicht hochgeladen werden (Fehlercode ' . $uploadedZip['error'] . ').';
        } else {
            if (!is_dir(TMP_DIR)) {
                @mkdir(TMP_DIR, 0775, true);
            }
            $stagedZipPath = rtrim(TMP_DIR, '/') . '/install-restore-' . bin2hex(random_bytes(8)) . '.zip';

            try {
                if (!move_uploaded_file($uploadedZip['tmp_name'], $stagedZipPath)) {
                    throw new \RuntimeException('Die hochgeladene Datei konnte nicht gespeichert werden.');
                }

                $sslCaPath = handleSslCaUpload();
                if ($sslCaPath !== null) {
                    $formValues['ssl_ca'] = $sslCaPath;
                }

                $pdo = Connection::fromConfig($formValues);

                $importer = new BackupArchiveImporter(
                    new MariadbDatabaseRestorer($pdo),
                    new MigrationRunner($pdo, MIGRATIONS_DIR),
                    UPLOADS_DIR,
                    TMP_DIR,
                );
                $summary = $importer->import($stagedZipPath);

                $state['db'] = $formValues;
                $state['migration_log'] = null;
                $state['restored'] = true;
                $state['restore_summary'] = $summary;
                $state['skip_admin'] = null;
                $state['step'] = 3;
                $formValues = [];
            } catch (\PDOException $e) {
                $error = 'Verbindung zur Datenbank fehlgeschlagen: ' . $e->getMessage();
            } catch (BackupArchiveException|\RuntimeException $e) {
                $error = 'Die Sicherung konnte nicht eingespielt werden: ' . $e->getMessage();
            } finally {
                @unlink($stagedZipPath);
            }
        }
    } elseif ($action === 'choose_admin_handling' && $state['step'] === 3 && $state['restored'] === true && $state['skip_admin'] === null && is_array($state['db'])) {
        // Antwort auf die Nachfrage in Schritt 3, nachdem eine Sicherung
        // wiederhergestellt wurde (siehe Begründung der Produktentscheidung
        // im Datei-Kommentar oben) — "skip": die im Backup bereits
        // enthaltenen Administrator-Zugänge bleiben die einzigen, der
        // Assistent schließt direkt ab; "create": zusätzlich wird wie im
        // Normalfall ein weiterer Administrator-Zugang abgefragt.
        $handling = (string) ($_POST['admin_handling'] ?? '');

        if ($handling === 'skip') {
            try {
                finishInstallation($state['db'], null, $state['restore_summary']);
                $state = &$_SESSION['installer'];
            } catch (\RuntimeException $e) {
                $error = $e->getMessage();
            }
        } elseif ($handling === 'create') {
            $state['skip_admin'] = false;
        } else {
            $error = 'Bitte eine der beiden Optionen auswählen.';
        }
    } elseif ($action === 'create_admin' && $state['step'] === 3 && is_array($state['db'])) {
        $email = trim((string) ($_POST['admin_email'] ?? ''));
        $password = (string) ($_POST['admin_password'] ?? '');
        $passwordConfirm = (string) ($_POST['admin_password_confirm'] ?? '');

        $passwordError = PasswordPolicy::validate($password);

        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $error = 'Bitte eine gültige E-Mail-Adresse für den Administrator-Zugang angeben.';
        } elseif ($passwordError !== null) {
            $error = $passwordError;
        } elseif ($password !== $passwordConfirm) {
            $error = 'Die beiden Passwort-Eingaben stimmen nicht überein.';
        } else {
            try {
                $pdo = Connection::fromConfig($state['db']);
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);

                // Die per Migration 008 seedende Zeile hat eine feste ID; wird
                // sie gefunden, wird sie auf die eingegebenen Zugangsdaten
                // umgestellt (kein zweiter Administrator-Datensatz). Fehlt sie
                // (z. B. weil sie zuvor schon einmal angepasst/gelöscht wurde),
                // wird ein neuer Administrator-Datensatz angelegt.
                $updateStmt = $pdo->prepare(
                    "UPDATE users SET email = :email, password_hash = :password_hash, must_change_password = 0, is_active = 1
                     WHERE id = '00000000-0000-4000-8000-000000000001' OR email = 'admin@materialverleih.local'"
                );
                $updateStmt->execute(['email' => $email, 'password_hash' => $passwordHash]);

                if ($updateStmt->rowCount() === 0) {
                    $insertStmt = $pdo->prepare(
                        'INSERT INTO users (id, email, password_hash, permissions, two_factor_method, must_change_password, is_active)
                         VALUES (:id, :email, :password_hash, :permissions, :two_factor_method, 0, 1)'
                    );
                    $insertStmt->execute([
                        'id' => Uuid::v4(),
                        'email' => $email,
                        'password_hash' => $passwordHash,
                        'permissions' => 'administration',
                        'two_factor_method' => 'none',
                    ]);
                }

                finishInstallation($state['db'], $email, $state['restore_summary']);
                $state = &$_SESSION['installer'];
            } catch (\PDOException $e) {
                $error = 'Der Administrator-Zugang konnte nicht gespeichert werden: ' . $e->getMessage();
            } catch (\RuntimeException $e) {
                $error = $e->getMessage();
            }
        }
    }
}

/**
 * Schließt den Assistenten ab: schreibt storage/database.yaml, legt die
 * Sperrdatei (storage/installed.lock) an und setzt die Installer-Sitzung
 * zurück. $successEmail ist null, wenn nach einer Wiederherstellung bewusst
 * KEIN zusätzlicher Administrator angelegt wurde (siehe
 * 'choose_admin_handling' oben) — Schritt 4 zeigt dann statt der
 * Administrator-E-Mail einen Hinweis auf die im Backup enthaltenen Zugänge.
 *
 * @param array<string, string> $db
 * @param array{tableCount: int|null, rowCount: int|null, assetFileCount: int|null, migrationsCaughtUp: int, exportedAt: string|null}|null $restoreSummary
 */
function finishInstallation(array $db, ?string $successEmail, ?array $restoreSummary): void
{
    writeDatabaseConfig($db);
    file_put_contents(LOCK_FILE, (new \DateTimeImmutable())->format('Y-m-d H:i:s') . " installiert\n");

    $sslEnabled = ($db['ssl_ca'] ?? '') !== '';

    $_SESSION['installer'] = ['step' => 1, 'db' => null, 'migration_log' => null, 'restored' => false, 'restore_summary' => null, 'skip_admin' => null];
    $_SESSION['installer']['step'] = 4;
    $_SESSION['installer']['success_email'] = $successEmail;
    $_SESSION['installer']['restore_summary'] = $restoreSummary;
    $_SESSION['installer']['ssl_enabled'] = $sslEnabled;
}

/**
 * App\Infrastructure\Config\FlatYamlConfig liest bewusst nur ein sehr
 * einfaches Format: ein Wert gilt als in Anführungszeichen gesetzt, wenn
 * Anfangs- UND Endzeichen übereinstimmend " oder ' sind — es gibt dabei
 * KEINE Escape-Sequenz für ein Anführungszeichen INNERHALB des Werts selbst.
 * Ein Passwort ohne Sonderzeichen braucht daher gar keine Anführungszeichen
 * (bleibt unverändert lesbar); enthält es eines der beiden Zeichen, wird mit
 * dem jeweils ANDEREN Anführungszeichen umschlossen. Enthält es BEIDE
 * Zeichen, gibt es mit diesem einfachen Format keine sichere Darstellung —
 * dann lieber eine klare Fehlermeldung als eine beim nächsten Einlesen
 * stillschweigend verstümmelte Konfigurationsdatei.
 */
function quoteYamlScalar(string $value): string
{
    $hasDouble = str_contains($value, '"');
    $hasSingle = str_contains($value, "'");

    if (!$hasDouble && !$hasSingle) {
        // Kein Anführungszeichen nötig, außer das Passwort wäre leer oder
        // bestünde nur aus Leerraum (würde sonst beim Einlesen zu "" statt
        // zum beabsichtigten Leerzeichen-Passwort).
        return trim($value) === $value && $value !== '' ? $value : '"' . $value . '"';
    }

    if ($hasDouble && !$hasSingle) {
        return "'" . $value . "'";
    }

    if ($hasSingle && !$hasDouble) {
        return '"' . $value . '"';
    }

    throw new \RuntimeException(
        'Das Datenbank-Passwort enthält sowohl \' als auch " — das lässt sich mit dem einfachen '
        . 'Konfigurationsdateiformat dieser Anwendung nicht sicher speichern. Bitte ein Passwort ohne '
        . 'diese Kombination vergeben (z. B. im Hosting-Verwaltungspanel neu setzen) und die Installation erneut versuchen.'
    );
}

/**
 * Verarbeitet das optionale SSL-CA-Upload-Feld (siehe Formular in Schritt 2)
 * — die eigentliche Logik steckt in App\Infrastructure\Installer\
 * SslCaUploadHandler (dort auch ausführlich dokumentiert), damit sie ohne
 * einen tatsächlichen HTTP-Upload testbar ist.
 *
 * @throws \RuntimeException bei einem Upload-Fehler oder offensichtlich ungültigem Dateiinhalt
 */
function handleSslCaUpload(): ?string
{
    return \App\Infrastructure\Installer\SslCaUploadHandler::handle($_FILES['ssl_ca_file'] ?? null, SSL_CA_FILE);
}

/** @param array<string, string> $config */
function writeDatabaseConfig(array $config): void
{
    $yaml = "# Automatisch vom Installer erzeugt (public/install.php) am " . (new \DateTimeImmutable())->format('Y-m-d H:i:s') . ".\n"
        . "# Liegt bewusst unter storage/ (außerhalb des öffentlichen Webroots public/)\n"
        . "# und wird von src/bootstrap.php automatisch an dieser Stelle gesucht.\n"
        . "host: " . $config['host'] . "\n"
        . "port: " . $config['port'] . "\n"
        . "database: " . $config['database'] . "\n"
        . "username: " . $config['username'] . "\n"
        . 'password: ' . quoteYamlScalar($config['password']) . "\n"
        . "charset: " . $config['charset'] . "\n";

    if (($config['ssl_ca'] ?? '') !== '') {
        $yaml .= "ssl_ca: " . $config['ssl_ca'] . "\n";
    }

    $directory = dirname(CONFIG_FILE);
    if (!is_dir($directory) || !is_writable($directory)) {
        throw new \RuntimeException(
            'Der Ordner "storage/" ist nicht beschreibbar. Bitte die folgenden Zeilen manuell als "storage/database.yaml" '
            . "speichern (Dateirechte auf dem Server anschließend einschränken):\n\n" . $yaml
        );
    }

    if (file_put_contents(CONFIG_FILE, $yaml) === false) {
        throw new \RuntimeException('database.yaml konnte nicht geschrieben werden. Bitte die Dateirechte von storage/ prüfen.');
    }

    @chmod(CONFIG_FILE, 0600);
}

$csrfToken = Csrf::token();
$step = $state['step'];
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Installation – Materialverleih Software</title>
    <?php require __DIR__ . '/../resources/views/_install_style.php'; ?>
</head>
<body>
<div class="install-shell">
    <div class="install-card">
        <div class="install-steps">
            <span class="<?= $step >= 1 ? 'is-active' : '' ?>">1. Voraussetzungen</span>
            <span class="<?= $step >= 2 ? 'is-active' : '' ?>">2. Datenbank</span>
            <span class="<?= $step >= 3 ? 'is-active' : '' ?>">3. Administrator</span>
            <span class="<?= $step >= 4 ? 'is-active' : '' ?>">4. Fertig</span>
        </div>

        <h1>Materialverleih Software – Installation</h1>

        <?php if ($error !== null): ?>
            <div class="install-alert install-alert-error"><?= nl2br(h($error)) ?></div>
        <?php endif; ?>

        <?php if ($step === 1): ?>
            <p>Dieser Assistent richtet die Anwendung einmalig ein: Systemvoraussetzungen prüfen, Datenbank verbinden und einrichten, Administrator-Zugang anlegen.</p>
            <ul class="install-checklist">
                <?php foreach ($requirements as $check): ?>
                    <li class="<?= $check['ok'] ? 'is-ok' : ($check['blocking'] ? 'is-error' : 'is-warning') ?>">
                        <?= $check['ok'] ? '✓' : ($check['blocking'] ? '✗' : '!') ?>
                        <?= h($check['label']) ?>
                    </li>
                <?php endforeach; ?>
            </ul>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                <input type="hidden" name="action" value="continue_to_db">
                <button class="install-btn" type="submit" <?= $requirementsOk ? '' : 'disabled' ?>>Weiter</button>
            </form>

        <?php elseif ($step === 2): ?>
            <p>Zugangsdaten zu einer bereits bestehenden MariaDB/MySQL-Datenbank angeben. Die Datenbank selbst muss vorher angelegt sein (z. B. im Hosting-Verwaltungspanel).</p>

            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                <input type="hidden" name="action" value="test_and_migrate">
                <label>Host
                    <input type="text" name="host" required value="<?= h($formValues['host'] ?? '127.0.0.1') ?>">
                </label>
                <label>Port
                    <input type="text" name="port" required value="<?= h($formValues['port'] ?? '3306') ?>">
                </label>
                <label>Datenbankname
                    <input type="text" name="database" required value="<?= h($formValues['database'] ?? 'materialverleih') ?>">
                </label>
                <label>Benutzername
                    <input type="text" name="username" required value="<?= h($formValues['username'] ?? '') ?>">
                </label>
                <label>Passwort
                    <input type="password" name="password" value="">
                </label>
                <label>Zeichensatz
                    <input type="text" name="charset" required value="<?= h($formValues['charset'] ?? 'utf8mb4') ?>">
                </label>
                <label>SSL-CA-Zertifikat (optional)
                    <input type="file" name="ssl_ca_file" accept=".pem,.crt,.cer,.ca-bundle,application/x-pem-file,application/x-x509-ca-cert">
                </label>
                <p class="install-hint">
                    Nur nötig, wenn die Verbindung zur Datenbank verschlüsselt (TLS/SSL) erfolgen soll — z. B. bei einer
                    verwalteten MariaDB/MySQL-Instanz eines Hosting-Anbieters. Die CA-Zertifikatsdatei (PEM-Format) wird
                    dafür vom Anbieter bereitgestellt. Ohne Angabe bleibt die Verbindung wie bisher unverschlüsselt.
                </p>
                <button class="install-btn" type="submit">Verbinden &amp; Tabellen einrichten</button>
            </form>

            <?php if (is_array($state['migration_log']) && $state['migration_log'] !== []): ?>
                <details class="install-log" open>
                    <summary>Protokoll des letzten Versuchs (<?= count($state['migration_log']) ?> Migration(en))</summary>
                    <ul>
                        <?php foreach ($state['migration_log'] as $entry): ?>
                            <li class="<?= $entry['status'] === 'failed' ? 'is-error' : 'is-ok' ?>">
                                <?= $entry['status'] === 'failed' ? '✗' : '✓' ?> <?= h($entry['file']) ?>
                                <?php if ($entry['status'] === 'already_applied'): ?> (bereits vorhanden)<?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </details>
            <?php endif; ?>

            <details class="install-restore">
                <summary>Stattdessen eine vorhandene Sicherung wiederherstellen</summary>
                <p class="install-hint">
                    Statt die Datenbank frisch einzurichten, kann hier eine zuvor unter
                    <code>Einstellungen&nbsp;&rarr;&nbsp;Sicherung</code> exportierte Sicherungs-ZIP eingespielt werden
                    (z. B. bei einem Server-Umzug). Dabei werden ALLE Tabelleninhalte der oben angegebenen Datenbank
                    sowie sämtliche gespeicherten Dateien (Artikelbilder usw.) durch den Inhalt der Sicherung ersetzt.
                </p>
                <form method="post" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                    <input type="hidden" name="action" value="test_and_restore">
                    <label>Host
                        <input type="text" name="host" required value="<?= h($formValues['host'] ?? '127.0.0.1') ?>">
                    </label>
                    <label>Port
                        <input type="text" name="port" required value="<?= h($formValues['port'] ?? '3306') ?>">
                    </label>
                    <label>Datenbankname
                        <input type="text" name="database" required value="<?= h($formValues['database'] ?? 'materialverleih') ?>">
                    </label>
                    <label>Benutzername
                        <input type="text" name="username" required value="<?= h($formValues['username'] ?? '') ?>">
                    </label>
                    <label>Passwort
                        <input type="password" name="password" value="">
                    </label>
                    <label>Zeichensatz
                        <input type="text" name="charset" required value="<?= h($formValues['charset'] ?? 'utf8mb4') ?>">
                    </label>
                    <label>SSL-CA-Zertifikat (optional)
                        <input type="file" name="ssl_ca_file" accept=".pem,.crt,.cer,.ca-bundle,application/x-pem-file,application/x-x509-ca-cert">
                    </label>
                    <label>Sicherungs-ZIP
                        <input type="file" name="backup_zip" accept=".zip,application/zip" required>
                    </label>
                    <button class="install-btn install-btn-danger" type="submit">Sicherung einspielen &amp; wiederherstellen</button>
                </form>
            </details>

        <?php elseif ($step === 3 && $state['restored'] === true && $state['skip_admin'] === null): ?>
            <p>
                Die Sicherung wurde eingespielt<?php if (is_array($state['restore_summary'])): ?>
                    (<?= (int) ($state['restore_summary']['tableCount'] ?? 0) ?> Tabelle(n),
                    <?= (int) ($state['restore_summary']['rowCount'] ?? 0) ?> Datensatz/Datensätze,
                    <?= (int) ($state['restore_summary']['assetFileCount'] ?? 0) ?> Datei(en)<?php if (($state['restore_summary']['migrationsCaughtUp'] ?? 0) > 0): ?>,
                    <?= (int) $state['restore_summary']['migrationsCaughtUp'] ?> neuere Migration(en) nachgezogen<?php endif; ?>)<?php endif; ?>.
                Die darin enthaltenen Administrator-Zugänge sind bereits gültig.
            </p>
            <p>Soll zusätzlich ein weiterer Administrator-Zugang angelegt werden?</p>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                <input type="hidden" name="action" value="choose_admin_handling">
                <input type="hidden" name="admin_handling" value="skip">
                <button class="install-btn install-btn-secondary" type="submit">Nein, vorhandene Zugänge genügen</button>
            </form>
            <form method="post" style="margin-top: 10px;">
                <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                <input type="hidden" name="action" value="choose_admin_handling">
                <input type="hidden" name="admin_handling" value="create">
                <button class="install-btn" type="submit">Ja, weiteren Administrator anlegen</button>
            </form>

        <?php elseif ($step === 3): ?>
            <p>
                <?php if ($state['restored'] === true): ?>
                    Zusätzlichen Administrator-Zugang festlegen.
                <?php else: ?>
                    Datenbank ist eingerichtet (<?= count(array_filter($state['migration_log'] ?? [], fn ($e) => $e['status'] === 'applied')) ?> Migration(en) neu eingespielt). Jetzt den ersten Administrator-Zugang festlegen.
                <?php endif; ?>
            </p>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                <input type="hidden" name="action" value="create_admin">
                <label>E-Mail-Adresse
                    <input type="email" name="admin_email" required value="<?= h($formValues['admin_email'] ?? '') ?>">
                </label>
                <label>Passwort (mind. 10 Zeichen)
                    <input type="password" name="admin_password" required minlength="10">
                </label>
                <label>Passwort wiederholen
                    <input type="password" name="admin_password_confirm" required minlength="10">
                </label>
                <button class="install-btn" type="submit">Administrator anlegen &amp; Installation abschließen</button>
            </form>

        <?php elseif ($step === 4): ?>
            <div class="install-alert install-alert-ok">Installation abgeschlossen.</div>
            <?php if ($state['success_email'] !== null): ?>
                <p>Der Administrator-Zugang <strong><?= h($state['success_email']) ?></strong> wurde angelegt. Die Datenbank-Zugangsdaten
                   wurden automatisch unter <code>storage/database.yaml</code> gespeichert.</p>
            <?php else: ?>
                <p>Die Datenbank-Zugangsdaten wurden automatisch unter <code>storage/database.yaml</code> gespeichert. Es gilt weiterhin
                   der/die in der wiederhergestellten Sicherung enthaltene(n) Administrator-Zugang/Zugänge.</p>
            <?php endif; ?>
            <?php if ($state['ssl_enabled'] ?? false): ?>
                <p class="install-hint">Die Verbindung zur Datenbank erfolgt verschlüsselt (TLS/SSL, CA-Zertifikat unter <code>storage/database-ssl-ca.pem</code> gespeichert).</p>
            <?php endif; ?>
            <p class="install-hint">
                Zum eigenen Schutz kann <code>public/install.php</code> nach der Installation gelöscht werden; notwendig ist das
                nicht, da die Seite dank <code>storage/installed.lock</code> keine weiteren Aktionen mehr zulässt.
            </p>
            <a class="install-btn" href="/login">Jetzt anmelden</a>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
