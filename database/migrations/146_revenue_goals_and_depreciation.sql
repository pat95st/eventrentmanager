-- Migration 146 (Nutzerwunsch): Umsatzziele je Jahr und Abschreibungen (AfA)
--  - revenue_goals: frei benennbare Umsatzziele/-grenzen (z. B.
--    "Liebhabereigrenze"). amount gilt für JEDES Jahr; kind unterscheidet
--    ein Ziel, das erreicht werden soll ('target'), von einer Grenze, die
--    nicht überschritten werden soll ('limit').
--  - revenue_goal_year_amounts: abweichender Betrag eines Ziels für ein
--    einzelnes Jahr (fehlt die Zeile, gilt revenue_goals.amount).
--  - depreciation_settings: Singleton-Zeile mit den Regeln der linearen
--    Abschreibung (Standard-Nutzungsdauer, GWG-Grenze für die
--    Sofortabschreibung, Behandlung des Anschaffungsjahres, Erinnerungswert).
--  - depreciation_category_useful_lives: abweichende Nutzungsdauer je
--    Artikelkategorie (gilt auch für deren Unterkategorien, solange diese
--    keine eigene Angabe haben).
-- Die Abschreibungen selbst werden nicht gespeichert, sondern aus
-- Anschaffungsdatum und Einkaufspreis der Inventarnummer berechnet (siehe
-- App\Domain\Depreciation\DepreciationCalculator).

CREATE TABLE revenue_goals (
    id         CHAR(36)      NOT NULL PRIMARY KEY,
    name       VARCHAR(100)  NOT NULL,
    kind       VARCHAR(10)   NOT NULL DEFAULT 'target',   -- target | limit
    amount     DECIMAL(12,2) NOT NULL,
    created_at DATETIME      NOT NULL,
    updated_at DATETIME      NOT NULL
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE revenue_goal_year_amounts (
    revenue_goal_id CHAR(36)      NOT NULL,
    year            SMALLINT      NOT NULL,
    amount          DECIMAL(12,2) NOT NULL,
    PRIMARY KEY (revenue_goal_id, year),
    CONSTRAINT fk_revenue_goal_year_amounts_goal FOREIGN KEY (revenue_goal_id) REFERENCES revenue_goals (id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE depreciation_settings (
    id                        TINYINT       NOT NULL PRIMARY KEY DEFAULT 1,
    default_useful_life_years SMALLINT      NOT NULL DEFAULT 5,
    low_value_threshold       DECIMAL(10,2) NOT NULL DEFAULT 800.00,   -- GWG-Grenze, 0 = keine Sofortabschreibung
    first_year_mode           VARCHAR(10)   NOT NULL DEFAULT 'monthly', -- monthly | full_year
    residual_value            DECIMAL(10,2) NOT NULL DEFAULT 0.00,     -- Erinnerungswert
    CONSTRAINT chk_depreciation_settings_singleton CHECK (id = 1)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

INSERT INTO depreciation_settings (id) VALUES (1);

CREATE TABLE depreciation_category_useful_lives (
    category_id       CHAR(36) NOT NULL PRIMARY KEY,
    useful_life_years SMALLINT NOT NULL,
    CONSTRAINT fk_depreciation_category_useful_lives_category FOREIGN KEY (category_id) REFERENCES categories (id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
