-- Migration für bereits bestehende Installationen: Standard-Seitengröße für
-- paginierte Listen (Artikel, Veranstaltungen, Kunden, ...), vom Benutzer
-- selbst unter /profil/darstellung änderbar (siehe
-- App\Infrastructure\Http\Pagination und App\Application\User\Command\
-- UpdateDefaultPageSize).
ALTER TABLE users
    ADD COLUMN default_page_size SMALLINT UNSIGNED NOT NULL DEFAULT 25 AFTER is_active;
