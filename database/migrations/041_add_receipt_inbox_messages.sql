-- Migration für bereits bestehende Installationen: die eigentlichen
-- eingesammelten Posteingangs-Nachrichten und ihre Anhänge (siehe
-- App\Domain\ReceiptInbox\ReceiptInboxMessage/ReceiptInboxAttachment,
-- App\Application\ReceiptInbox\Command\ImportReceiptEmailsHandler).
--
-- message_id ist die Message-ID aus dem E-Mail-Header (RFC 5322) und dient
-- als reines DB-seitiges Dedup-Kriterium — das Postfach selbst wird beim
-- Import NIEMALS verändert (keine "gelesen"-Markierung, keine
-- Verschiebung/Löschung), siehe ImapClient/Pop3Client.
--
-- Ein Anhang wird erst dann in einen "echten" Beleg umgewandelt, wenn ein
-- Benutzer das im Posteingang explizit auslöst (siehe
-- App\Application\ReceiptInbox\Command\ConvertReceiptInboxAttachmentToReceipt);
-- converted_receipt_id bleibt bis dahin NULL.
USE materialverleih;

CREATE TABLE receipt_inbox_messages (
    id            CHAR(36)     NOT NULL PRIMARY KEY,
    -- VARCHAR(998): RFC 5322 begrenzt eine Kopfzeile (inkl. "Message-ID: ")
    -- auf 998 Zeichen, das ist die praktisch größtmögliche Message-ID-Länge.
    message_id    VARCHAR(998) NOT NULL,
    from_address  VARCHAR(255) NOT NULL,
    subject       VARCHAR(998) NULL,
    received_at   DATETIME     NULL,
    imported_at   DATETIME     NOT NULL,
    UNIQUE KEY uq_receipt_inbox_messages_message_id (message_id(255))
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE receipt_inbox_attachments (
    id                    CHAR(36)     NOT NULL PRIMARY KEY,
    message_id            CHAR(36)     NOT NULL,
    original_filename     VARCHAR(255) NOT NULL,
    stored_filename       VARCHAR(255) NOT NULL,
    mime_type             VARCHAR(100) NOT NULL,
    size_bytes            INT UNSIGNED NOT NULL,
    -- NULL = noch nicht zu einem Beleg umgewandelt (siehe
    -- ReceiptInboxController/receipts/inbox.php für den "Zu Beleg
    -- umwandeln"-Button). ON DELETE SET NULL statt CASCADE: wird der
    -- daraus erzeugte Beleg später gelöscht, soll der Anhang im
    -- Posteingang als Datensatz erhalten bleiben (nur die Verknüpfung
    -- verschwindet).
    converted_receipt_id  CHAR(36)     NULL,
    created_at            DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_receipt_inbox_attachments_message
        FOREIGN KEY (message_id) REFERENCES receipt_inbox_messages (id) ON DELETE CASCADE,
    CONSTRAINT fk_receipt_inbox_attachments_receipt
        FOREIGN KEY (converted_receipt_id) REFERENCES receipts (id) ON DELETE SET NULL,
    INDEX idx_receipt_inbox_attachments_message (message_id),
    INDEX idx_receipt_inbox_attachments_converted (converted_receipt_id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
