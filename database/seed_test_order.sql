-- =====================================================================
-- seed_test_order.sql — dummy order for local support-form testing
-- =====================================================================
-- Inserts ONE realistic order into the EXISTING `orders` table so you can
-- run the full flow: Email -> OTP -> Verify -> Order lookup -> Order
-- dropdown -> Fish SKU selection -> Ticket submission.
--
-- Does NOT touch `tickets` or `ticket_files` (per instructions), and does
-- NOT create any new table. Just an INSERT against the schema you already
-- created from bettashop_db (1).sql.
--
-- >>> BEFORE RUNNING: replace the email below with your real test email. <<<
-- Everything else is realistic placeholder data you can freely edit.
-- =====================================================================

INSERT INTO `orders`
    (`order_id`, `first_name`, `last_name`, `email`, `order_date`, `country`, `total_value`, `sku`)
VALUES
    ('BB-2026-00042', 'Jamie', 'Rivera', 'REPLACE_WITH_YOUR_TEST_EMAIL@example.com', '2026-08-15 10:32:00', 'United States', 149.99, 'BETTA-HM-BLUE-01');

-- Optional: add a second order for the same email, to confirm the
-- dropdown correctly lists multiple orders (newest first, per the
-- existing ORDER BY order_date DESC in users::getUserOrdersByEmail()).
--
-- INSERT INTO `orders`
--     (`order_id`, `first_name`, `last_name`, `email`, `order_date`, `country`, `total_value`, `sku`)
-- VALUES
--     ('BB-2026-00017', 'Jamie', 'Rivera', 'REPLACE_WITH_YOUR_TEST_EMAIL@example.com', '2026-06-02 14:05:00', 'United States', 89.50, 'BETTA-PK-DRAGON-02');
