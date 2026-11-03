-- Migration 116: Schriftarten für PDF-Dokumente und -Listen (Nutzerwunsch).
-- Schriften werden einmalig von Google Fonts geladen (oder als TTF
-- hochgeladen), unter storage/uploads/fonts/<id>/ abgelegt und je Bereich
-- (Text, Überschriften, Tabellen, Fußzeile) zugeordnet. In die PDFs wird
-- nur der benötigte Zeichenvorrat eingebettet.

CREATE TABLE pdf_fonts (
    id                  CHAR(36)     NOT NULL PRIMARY KEY,
    family              VARCHAR(150) NOT NULL,
    source              VARCHAR(20)  NOT NULL DEFAULT 'google', -- google | upload
    regular_file        VARCHAR(255) NOT NULL,
    bold_file           VARCHAR(255) NULL,
    italic_file         VARCHAR(255) NULL,
    bold_italic_file    VARCHAR(255) NULL,
    created_at          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_pdf_fonts_family (family)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE pdf_font_assignments (
    role        VARCHAR(20) NOT NULL PRIMARY KEY, -- body | heading | table | footer
    font_id     CHAR(36)    NULL,
    updated_at  DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_pdf_font_assignments_font
        FOREIGN KEY (font_id) REFERENCES pdf_fonts (id) ON DELETE SET NULL
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
