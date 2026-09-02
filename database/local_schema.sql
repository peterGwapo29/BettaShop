-- local_schema.sql
-- ============================================================
-- LOCAL DEVELOPMENT SCHEMA — PLACEHOLDER, NOT CONFIRMED PRODUCTION SCHEMA
-- ============================================================
-- Run this in phpMyAdmin (or via the mysql CLI) against your local
-- XAMPP MySQL/MariaDB instance to create the "bettashop_db" database
-- and the minimum table needed for the support form's OTP + order
-- lookup step to run locally without a fatal "table doesn't exist" error.
--
-- IMPORTANT — this "orders" table is a GUESS, not a confirmed schema:
--   - Columns order_id, first_name, last_name, email, created_at are
--     confirmed to exist in production because they're referenced in
--     reference/jaylyn_support_template.php's SQL query.
--   - The column "order_date" is NOT confirmed anywhere in the provided
--     files. It's inferred only because index.php's JS reads
--     `order.order_date` from the JSON that users::getUserOrdersByEmail()
--     (a file that was not provided) is expected to return. It is possible
--     the real column is actually "created_at" reused as the display date,
--     or a differently-named column.
--   - country / total_value are included because jaylyn_support_template.php
--     references them, but they are NOT required by the current support
--     form flow — included for forward-compatibility only.
--
-- Tables joined only by the (not-yet-wired) admin template — purchases,
-- schedule, users (transhipper accounts) — are intentionally NOT created
-- here, since that page isn't part of this task's scope and guessing
-- three more schemas on top of this one would compound the risk of
-- diverging from production. Add them when Stage 2 work actually starts.
--
-- Left EMPTY (no seed rows) per instructions — this only gets you a
-- non-fatal "no orders found" result locally, not real test data.
-- ============================================================

CREATE DATABASE IF NOT EXISTS bettashop_db
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE bettashop_db;

CREATE TABLE IF NOT EXISTS orders (
    order_id     VARCHAR(64)    NOT NULL PRIMARY KEY,
    first_name   VARCHAR(100)   DEFAULT NULL,
    last_name    VARCHAR(100)   DEFAULT NULL,
    email        VARCHAR(255)   DEFAULT NULL,
    order_date   DATETIME       DEFAULT NULL,  -- UNCONFIRMED, see note above
    country      VARCHAR(100)   DEFAULT NULL,
    total_value  DECIMAL(10,2)  DEFAULT 0,
    sku          VARCHAR(255)   DEFAULT NULL,
    created_at   TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    modified_at  TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_orders_email (email)
);

-- No rows inserted — table intentionally left empty.
