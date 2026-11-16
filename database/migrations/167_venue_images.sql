-- Migration 167: Photos of a venue with an optional description
-- (e.g. access route, loading dock, power distribution, stage).
--
--  - venue_images.file_path: relative to storage/uploads/venues,
--    e.g. "<venue-id>/<image-id>.jpg".
--  - venue_images.description: free text shown below the enlarged image.

CREATE TABLE venue_images (
    id          CHAR(36)     NOT NULL PRIMARY KEY,
    venue_id    CHAR(36)     NOT NULL,
    file_path   VARCHAR(500) NOT NULL,
    description TEXT         NULL,
    position    INT UNSIGNED NOT NULL DEFAULT 0,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_venue_images_venue
        FOREIGN KEY (venue_id) REFERENCES venues (id) ON DELETE CASCADE,
    INDEX idx_venue_images_venue (venue_id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
