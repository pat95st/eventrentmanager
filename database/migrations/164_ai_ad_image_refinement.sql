-- Migration 164: Refine existing AI ad images.
--
-- An ad image can be sent back to the image model together with change
-- requests ("Überarbeiten"); the result is stored as a NEW ad image (the
-- history is kept). source_ad_image_id points to the ad image it was derived
-- from and becomes NULL when that one is deleted.

ALTER TABLE article_ad_images
    ADD COLUMN source_ad_image_id CHAR(36) NULL AFTER source_article_image_id,
    ADD CONSTRAINT fk_article_ad_images_source_ad_image
        FOREIGN KEY (source_ad_image_id) REFERENCES article_ad_images (id) ON DELETE SET NULL;
