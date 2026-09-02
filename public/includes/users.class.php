<?php
/**
 * users.class.php — PLACEHOLDER, NOT THE PRODUCTION FILE
 * =========================================================
 * otp_verify.api.php calls users::getUserOrdersByEmail($pdo, $email), but
 * this class file was NOT among the files provided in Bettashop.zip.
 *
 * The client mentioned "users.class.inc.php" in chat, but the actual code
 * in otp_verify.api.php requires "users.class.php" (different filename —
 * worth double-checking with the client which one is correct, or whether
 * both exist and do different things).
 *
 * This stand-in exists ONLY so the support form doesn't fatal-error locally.
 * The query below is a best-effort guess based on how its return value is
 * used elsewhere in the provided files:
 *   - otp_verify.api.php reads $order_data[0]['first_name'] / ['last_name']
 *   - index.php's JS reads order.order_id and order.order_date
 *
 * The "orders" table columns referenced in reference/jaylyn_support_template.php
 * are: order_id, first_name, last_name, email, country, total_value, created_at
 * — notably NO "order_date" column appears there. It's possible order_date
 * should actually be created_at, or a different column entirely.
 *
 * ACTION NEEDED: get the real users.class.php from the client, or confirm
 * the exact orders table schema, before treating this as final.
 */

class users
{
    /**
     * @param PDO    $pdo
     * @param string $email
     * @return array<int, array{order_id:string, order_date:?string, first_name:?string, last_name:?string, sku:?string}>
     */
    public static function getUserOrdersByEmail(PDO $pdo, string $email): array
    {
        $stmt = $pdo->prepare("
            SELECT order_id, order_date, first_name, last_name, sku
            FROM orders
            WHERE email = :email
            ORDER BY order_date DESC
        ");
        $stmt->execute(['email' => $email]);

        return $stmt->fetchAll();
    }
}
