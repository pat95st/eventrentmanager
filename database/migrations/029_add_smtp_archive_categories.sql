-- Migration für bereits bestehende Installationen: steuert, welche
-- Kategorien ausgehender Mails bei gesetzter Archivierungsadresse
-- tatsächlich per BCC archiviert werden (siehe App\Domain\Mail\MailCategory
-- sowie App\Domain\Settings\SmtpSettings::shouldArchive()). Bisher wurde bei
-- gesetzter Archivierungsadresse ausnahmslos JEDE Mail archiviert — das
-- bleibt für bestehende Installationen unverändert, bis ein Administrator
-- im Einstellungsbereich einzelne Kategorien abwählt.
--
-- NULL/leer bedeutet auf Domain-Ebene "alle Kategorien archivieren" (siehe
-- SmtpSettings::shouldArchive()), wird hier aber bewusst NICHT als NULL
-- belassen, sondern explizit mit allen Kategorien vorbefüllt, damit die
-- bestehende Zeile nicht stillschweigend vom NULL-Fallback abhängt.
USE materialverleih;

ALTER TABLE smtp_settings
    ADD COLUMN archive_categories TEXT NULL AFTER archive_bcc_address;

UPDATE smtp_settings
SET archive_categories = 'login_otp,password_setup_invite,password_reset,offer_pdf,packing_list_pdf,test_mail'
WHERE id = 1;
