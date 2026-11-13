-- Migration für bereits bestehende Installationen:
--   Mobile Bühnen aus Bühnenelementen (Podesten).
--   1. venue_stage_elements: vorhandener Elementbestand einer mobilen Bühne
--      je Standardgröße (2x1 | 1x1 | 2x0.5 | 1x0.5, siehe
--      App\Domain\Venue\StageElementSize).
--   2. venue_stages.width_m/depth_m werden NULLABLE: Bei einer mobilen
--      Bühne, die über ihren Elementbestand beschrieben ist, stehen die Maße
--      nicht fest — die baubaren Größen werden bei jeder Anzeige live aus dem
--      Bestand vorgeschlagen (App\Domain\Venue\StageElementPlanner).
ALTER TABLE venue_stages
    MODIFY COLUMN width_m DECIMAL(5,2) NULL,
    MODIFY COLUMN depth_m DECIMAL(5,2) NULL;

CREATE TABLE venue_stage_elements (
    stage_id      CHAR(36)          NOT NULL,
    element_size  VARCHAR(10)       NOT NULL, -- 2x1 | 1x1 | 2x0.5 | 1x0.5
    quantity      SMALLINT UNSIGNED NOT NULL,
    PRIMARY KEY (stage_id, element_size),
    CONSTRAINT fk_venue_stage_elements_stage
        FOREIGN KEY (stage_id) REFERENCES venue_stages (id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
