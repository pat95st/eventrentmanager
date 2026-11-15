-- Migration 153: additional storage targets (Einstellungen › Ziellaufwerke).
--  - Dropbox, Microsoft OneDrive, Google Drive and Box are connected via the
--    OAuth 2.0 authorization code flow: the administrator registers an app
--    with the provider, enters its client ID/secret and then clicks
--    "Verbinden". Refresh/access tokens are stored here and refreshed
--    automatically (Box and Microsoft rotate refresh tokens).
--  - iCloud Drive has no public file API for servers; it is written to as a
--    locally synchronised folder (base_path = absolute directory on the
--    application server, e.g. the iCloud Drive folder on a Mac or an rclone
--    mount).
-- Credentials are stored in plain text like the other storage credentials
-- (see 044_add_document_storage_targets.sql).

ALTER TABLE document_storage_targets
    MODIFY COLUMN protocol ENUM('webdav', 'ftps', 'sftp', 'scp', 's3', 'nextcloud', 'owncloud', 'dropbox', 'onedrive', 'google_drive', 'box', 'icloud') NOT NULL,
    ADD COLUMN oauth_client_id               VARCHAR(255) NULL AFTER s3_use_path_style,
    ADD COLUMN oauth_client_secret           VARCHAR(500) NULL AFTER oauth_client_id,
    ADD COLUMN oauth_tenant                  VARCHAR(255) NULL AFTER oauth_client_secret,
    ADD COLUMN oauth_refresh_token           TEXT         NULL AFTER oauth_tenant,
    ADD COLUMN oauth_access_token            TEXT         NULL AFTER oauth_refresh_token,
    ADD COLUMN oauth_access_token_expires_at DATETIME     NULL AFTER oauth_access_token,
    ADD COLUMN oauth_account_label           VARCHAR(255) NULL AFTER oauth_access_token_expires_at,
    ADD COLUMN oauth_connected_at            DATETIME     NULL AFTER oauth_account_label;
