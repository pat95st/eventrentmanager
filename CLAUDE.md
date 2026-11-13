# Hinweise für Claude

## Datenbank-Migrationen (`database/migrations/`)

- **Kein `USE <datenbank>;`** (und kein `CREATE DATABASE`) in Migrationen.
  MigrationRunner, Web-Installer und `mysql <datenbank> < datei.sql` sind
  bereits mit der richtigen Datenbank verbunden; ein fest verdrahteter Name
  bricht bei abweichendem Datenbanknamen ab oder schreibt in die falsche
  Datenbank. `SchemaSnapshotTest` schlägt fehl, wenn eine Migration `USE`
  enthält.
- **Jede neue Migration auch in `database/schema.sql` übernehmen**: ihren
  Inhalt als eigenen `-- --- NNN_name.sql ---`-Abschnitt vor dem Block
  "Stand dieses Snapshots" anhängen (oder in die bestehenden
  `CREATE TABLE`-Definitionen einarbeiten) **und** den Dateinamen in die
  `INSERT INTO schema_migrations`-Liste am Ende eintragen. Niemals einen
  Dateinamen eintragen, dessen Inhalt im Schema fehlt.
  `tests/unit/Infrastructure/Migrations/SchemaSnapshotTest.php` prüft das.
