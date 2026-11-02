-- Migration 114: Bilder vom befüllten Case und Hinweistext je Case-Exemplar
-- (Nutzerwunsch). Beides erscheint auf der Stückliste (PDF) des Exemplars.

ALTER TABLE case_inventory_numbers
    ADD COLUMN notice_html TEXT NULL AFTER acquisition_date; -- bereinigtes HTML, siehe HtmlSanitizer

CREATE TABLE case_inventory_number_images (
    id                          CHAR(36)     NOT NULL PRIMARY KEY,
    case_inventory_number_id    CHAR(36)     NOT NULL,
    file_path                   VARCHAR(255) NOT NULL, -- relativ zu storage/cases
    position                    INT UNSIGNED NOT NULL DEFAULT 0,
    created_at                  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_case_inventory_number_images_inventory_number
        FOREIGN KEY (case_inventory_number_id) REFERENCES case_inventory_numbers (id) ON DELETE CASCADE,
    INDEX idx_case_inventory_number_images_inventory_number (case_inventory_number_id, position)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
