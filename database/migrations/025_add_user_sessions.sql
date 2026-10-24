-- Migration für bereits bestehende Installationen: serverseitige Nachverfolgung
-- aktiver Anmelde-Sitzungen (siehe App\Domain\Auth\UserSession). Die PHP-Session
-- selbst bleibt unverändert dateibasiert (session_start()/$_SESSION, siehe
-- App\Infrastructure\Http\Auth\Session) — user_sessions ist lediglich ein
-- serverseitiges Register "welche Session-IDs sind für welchen Benutzer aktuell
-- gültig", damit sich eine Sitzung von einem ANDEREN Gerät/Tab aus remote
-- beenden lässt (siehe AuthGuard::guard(): fehlt beim nächsten Request die Zeile
-- zur aktuellen Session-ID, wird wie bei einem bereits abgemeldeten Benutzer
-- verfahren). id ist bewusst die PHP-Session-ID selbst (kein separater UUID),
-- damit der Zusammenhang 1:1 und ohne zusätzliche Zuordnungstabelle bleibt.
USE materialverleih;

CREATE TABLE user_sessions (
    id            VARCHAR(128) NOT NULL PRIMARY KEY,
    user_id       VARCHAR(36)  NOT NULL,
    user_agent    TEXT         NULL,
    ip_address    VARCHAR(45)  NULL,
    created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_seen_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_user_sessions_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE INDEX idx_user_sessions_user_id ON user_sessions (user_id);
