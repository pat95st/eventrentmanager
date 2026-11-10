-- Migration 141: Dry-Hire-Preise
--  - article_dry_hire_prices: Dry-Hire-Tagesmietsatz je Artikel (zusätzlich
--    zum normalen Tagesmietsatz); ohne Eintrag gilt der normale Preis
--  - customer_dry_hire: Kunden, die Dry-Hire-Preise bekommen
--  - dry_hire_tiers: Staffel „ab Tag X kostet jeder weitere Tag P % des
--    Dry-Hire-Tagesmietsatzes“ – gilt für beliebig lange Mietdauer
--  - document_dry_hire: ob ein Angebot/AB/Rechnung mit Dry-Hire-Preisen
--    erstellt wurde (Checkbox im Formular)
CREATE TABLE IF NOT EXISTS article_dry_hire_prices (
    article_id CHAR(36) NOT NULL PRIMARY KEY,
    daily_price DECIMAL(10,2) NOT NULL,
    updated_at DATETIME NOT NULL,
    CONSTRAINT fk_dry_hire_article FOREIGN KEY (article_id) REFERENCES articles (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS customer_dry_hire (
    customer_id CHAR(36) NOT NULL PRIMARY KEY,
    created_at DATETIME NOT NULL,
    CONSTRAINT fk_dry_hire_customer FOREIGN KEY (customer_id) REFERENCES customers (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS dry_hire_tiers (
    from_day INT NOT NULL PRIMARY KEY,
    percentage DECIMAL(6,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO dry_hire_tiers (from_day, percentage) VALUES (1, 100.00), (2, 50.00), (8, 25.00);

CREATE TABLE IF NOT EXISTS document_dry_hire (
    document_type VARCHAR(30) NOT NULL,
    document_id CHAR(36) NOT NULL,
    enabled TINYINT(1) NOT NULL,
    PRIMARY KEY (document_type, document_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
