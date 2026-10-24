-- Migration für bereits bestehende Installationen: Sprachpräferenz des
-- Benutzers (Deutsch/Englisch) für die Oberfläche, vom Benutzer selbst
-- unter /profil/darstellung änderbar (siehe
-- App\Infrastructure\Http\Translator/Locale und App\Application\User\
-- Command\UpdateLocale). Für anonyme/nicht angemeldete Seiten (z. B.
-- Login) wird die Sprache stattdessen nur in der Session gehalten, siehe
-- App\Infrastructure\Http\Locale::resolveFromSession().
ALTER TABLE users
    ADD COLUMN locale VARCHAR(5) NOT NULL DEFAULT 'de' AFTER default_page_size;
