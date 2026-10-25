-- Startseite/Dashboard mit konfigurierbaren Bereichen (siehe
-- App\Domain\Dashboard\DashboardWidget, App\Infrastructure\Http\Controllers\
-- DashboardController). Jeder Benutzer kann unter /profil/dashboard
-- festlegen, welche Bereiche für ihn angezeigt werden.
--
-- NULL = noch nicht angepasst -> es werden alle Bereiche angezeigt, für die
-- der Benutzer ohnehin ein Recht hat. Ein (ggf. leerer) String bedeutet
-- "explizit konfiguriert" (kommaseparierte Liste von Widget-Schlüsseln).
ALTER TABLE users ADD COLUMN dashboard_widgets TEXT NULL AFTER notify_on_new_receipt_email;
