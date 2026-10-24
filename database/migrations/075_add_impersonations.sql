-- Protokoll für die Administrator-Impersonation ("Als Benutzer anmelden").
CREATE TABLE IF NOT EXISTS impersonations (
    id VARCHAR(36) NOT NULL PRIMARY KEY,
    admin_user_id VARCHAR(36) NOT NULL,
    target_user_id VARCHAR(36) NOT NULL,
    started_at DATETIME NOT NULL,
    ended_at DATETIME NULL,
    INDEX idx_impersonations_admin (admin_user_id),
    INDEX idx_impersonations_target (target_user_id),
    INDEX idx_impersonations_started_at (started_at),
    CONSTRAINT fk_impersonations_admin FOREIGN KEY (admin_user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_impersonations_target FOREIGN KEY (target_user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
