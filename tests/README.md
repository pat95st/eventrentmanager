# Tests

Das Projekt wird mit [Codeception](https://codeception.com/) getestet (über
Composer installiert, siehe `composer.json` → `require-dev`). Es gibt zwei
Suiten:

- **Unit** (`tests/unit/`): reine Domain-/Application-Logik (Entities,
  CQRS-Command-/Query-Handler, die CalDAV-/`.mobileconfig`-Bausteine).
  Läuft **komplett ohne Datenbank und ohne laufende Anwendung** — die
  Application-Handler werden gegen selbst geschriebene In-Memory-Fakes der
  jeweiligen Repository-Interfaces getestet (siehe `tests/_support/Fakes/`),
  nicht gegen die echten `Mariadb*Repository`-Klassen.
- **Acceptance** (`tests/acceptance/`): prüft die Anwendung als Ganzes über
  echtes HTTP (wie ein Browser, ohne JavaScript auszuführen) gegen eine
  laufende Instanz + eine eigene **Test-Datenbank**. Diese Suite braucht
  Vorbereitung, siehe unten.

## Einmalige Einrichtung

```bash
composer install
```

Dabei wird automatisch `vendor/bin/codecept build` ausgeführt (siehe
`composer.json` → `post-install-cmd`/`post-update-cmd`), was die von den
aktivierten Modulen bereitgestellten `$I->...()`-Methoden für die
Actor-Klassen (`UnitTester`/`AcceptanceTester`) generiert. Das muss danach
nur erneut laufen, wenn sich die Modul-Konfiguration in `tests/unit.suite.yml`
bzw. `tests/acceptance.suite.yml` ändert — dann genügt erneut
`vendor/bin/codecept build`.

## Unit-Tests ausführen

```bash
composer test:unit
# oder direkt:
vendor/bin/codecept run unit
```

Keine weitere Vorbereitung nötig.

## Acceptance-Tests ausführen

1. Eine **separate** MariaDB-Datenbank für Tests anlegen (niemals gegen die
   Produktivdatenbank laufen lassen — das Db-Modul **leert die konfigurierte
   Datenbank vor jedem einzelnen Test komplett** und lädt sie aus
   `database/schema.sql` neu, inklusive des dort geseedeten
   Standard-Administrators `admin@materialverleih.local`).

   ```sql
   CREATE DATABASE materialverleih_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   CREATE USER 'materialverleih_test'@'127.0.0.1' IDENTIFIED BY 'ein-sicheres-test-passwort';
   GRANT ALL PRIVILEGES ON materialverleih_test.* TO 'materialverleih_test'@'127.0.0.1';
   ```

2. `tests/.env.testing.dist` nach `tests/.env.testing` kopieren und mit den
   echten Zugangsdaten aus Schritt 1 befüllen (diese Datei ist gitignored).

3. Eine zweite, separate `database.yaml` für die Test-Instanz der Anwendung
   anlegen (zeigt auf dieselbe Test-Datenbank wie `tests/.env.testing`, siehe
   `database.example.yaml` als Vorlage), und die Anwendung damit über den
   PHP-Entwicklungsserver starten (in einem eigenen Terminal laufen lassen):

   ```bash
   DATABASE_CONFIG_PATH=/pfad/zu/test-database.yaml php -S 127.0.0.1:8000 -t public router.php
   ```

4. In einem zweiten Terminal die Tests ausführen:

   ```bash
   composer test:acceptance
   # oder direkt:
   vendor/bin/codecept run acceptance
   ```

### Warum eine laufende Anwendung UND eine Datenbank?

Die Acceptance-Suite prüft über das `PhpBrowser`-Modul echtes HTTP gegen
`APP_URL` (siehe `tests/.env.testing`) — es wird also eine tatsächlich
laufende Instanz der Anwendung gebraucht, kein In-Process-Aufruf. Das
`Db`-Modul greift zusätzlich direkt auf dieselbe Datenbank zu, um Test-Daten
vorzubereiten (`$I->haveInDatabase(...)`) und Ergebnisse zu prüfen
(`$I->seeInDatabase(...)`), unabhängig von dem, was über HTTP sichtbar ist.

## Alle Tests auf einmal

```bash
composer test
```

(Setzt voraus, dass die Vorbereitung für die Acceptance-Suite oben bereits
erledigt ist — sonst schlägt nur dieser Teil fehl, die Unit-Suite läuft
trotzdem durch.)
