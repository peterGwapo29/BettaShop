-- tickets_schema.sql
-- ============================================================
-- Adds the `tickets` table (DOA/refund requests) and a companion
-- `ticket_files` table for uploaded photos.
--
-- Run this in phpMyAdmin against bettashop_db (SQL tab), same way as
-- database/local_schema.sql. Safe to re-run — uses CREATE TABLE IF NOT EXISTS.
--
-- Design notes:
--   - `order_id` is VARCHAR(64) to match the placeholder `orders.order_id`
--     column type in local_schema.sql. If the real production `orders`
--     table uses a different type (e.g. INT), this should be adjusted to
--     match before pointing at production data.
--   - No foreign key constraint from tickets.order_id -> orders.order_id
--     is added, since the real orders table's exact structure isn't
--     confirmed yet (see local_schema.sql's notes). Add one once confirmed.
--   - `fish_sku` is a simple string column (comma-separated if multiple)
--     rather than a normalized SKU table, because there's no confirmed
--     per-order item/SKU data available anywhere in the provided files
--     (see notes in public/support/index.php about the SKU checkbox gap).
--   - `status` + `admin_response` exist now (even though today's task is
--     just ticket creation) because the task requires the table to hold
--     "all information required to review the request later" — this is
--     what Stage 2 (approve/deny) will read and write.
-- ============================================================

USE bettashop_db;

CREATE TABLE IF NOT EXISTS tickets (
    ticket_id         INT AUTO_INCREMENT PRIMARY KEY,
    ticket_ref        VARCHAR(20)     NOT NULL UNIQUE,   -- e.g. "DOA-4F91C2", shown to the customer
    email             VARCHAR(255)    NOT NULL,           -- verified email at time of submission
    first_name        VARCHAR(100)    NOT NULL,
    last_name         VARCHAR(100)    NOT NULL,
    order_id          VARCHAR(64)     NOT NULL,
    delivery_date     DATE            NOT NULL,
    fish_sku          VARCHAR(255)    NOT NULL,           -- comma-separated if multiple; see notes above
    doa_count         INT             NOT NULL,
    resolution        VARCHAR(50)     NOT NULL,           -- 'store-credit' | 'replacement' | 'other'
    resolution_other  VARCHAR(500)    DEFAULT NULL,
    description       TEXT            NOT NULL,
    status            VARCHAR(20)     NOT NULL DEFAULT 'pending', -- 'pending' | 'approved' | 'denied'
    admin_response    TEXT            DEFAULT NULL,        -- populated in Stage 2 (esp. on denial)
    created_at        TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    modified_at       TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_tickets_email (email),
    INDEX idx_tickets_order_id (order_id),
    INDEX idx_tickets_status (status)
);

CREATE TABLE IF NOT EXISTS ticket_files (
    file_id            INT AUTO_INCREMENT PRIMARY KEY,
    ticket_id          INT             NOT NULL,
    original_filename  VARCHAR(255)    NOT NULL,          -- as uploaded by the customer (display only, never trusted for paths)
    stored_filename    VARCHAR(255)    NOT NULL,          -- randomly generated name actually used on disk
    mime_type          VARCHAR(100)    NOT NULL,           -- detected server-side, not trusted from the browser
    size_bytes         INT             NOT NULL,
    created_at         TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    modified_at        TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_ticket_files_ticket
        FOREIGN KEY (ticket_id) REFERENCES tickets(ticket_id)
        ON DELETE CASCADE,
    INDEX idx_ticket_files_ticket_id (ticket_id)
);
