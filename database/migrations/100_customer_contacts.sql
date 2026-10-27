-- Mehrere Ansprechpartner je Kunde (siehe
-- App\Domain\Customer\CustomerContactRepositoryInterface) — insbesondere für
-- Firmenkunden, bei denen der Ansprechpartner öfter wechselt, als eine
-- einzelne Kontaktperson pro Kunde abbilden könnte. ON DELETE CASCADE: wird
-- der Kunde gelöscht, sind seine Ansprechpartner gegenstandslos.
--
-- "job_title" statt "position" als Spaltenname: POSITION ist in
-- MySQL/MariaDB eine (nicht reservierte, aber potenziell verwirrende)
-- SQL-Funktion — der eindeutige Name vermeidet jede Unklarheit, ohne dass
-- Abfragen das Wort quoten müssten.

CREATE TABLE customer_contacts (
    id          CHAR(36)     NOT NULL PRIMARY KEY,
    customer_id CHAR(36)     NOT NULL,
    name        VARCHAR(255) NOT NULL,
    job_title   VARCHAR(150) NULL,
    phone       VARCHAR(50)  NULL,
    email       VARCHAR(255) NULL,
    sort_order  INT          NOT NULL DEFAULT 0,
    CONSTRAINT fk_customer_contacts_customer
        FOREIGN KEY (customer_id) REFERENCES customers (id) ON DELETE CASCADE,
    INDEX idx_customer_contacts_customer (customer_id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
