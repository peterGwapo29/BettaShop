<?php

require_once "../../includes/dbh.inc.php";
require_once "../../includes/config.inc.php";
require_once "includes/ValidateUser.inc.php";
require_once "../includes/display_products.class.php";

validateUserCredentials();

function getLabels($orderId) {

    $basePath = realpath($_SERVER['DOCUMENT_ROOT'] . '/../var/labels');

    if ($basePath === false) return []; // safety

    $dir = $basePath . '/' . $orderId;

    if (!is_dir($dir)) return [];

    $files = glob($dir . "/*.pdf");
    return array_map('basename', $files);
}

// Load all orders
$stmt = $pdo->prepare("
    SELECT
        o.order_id,
        CONCAT(COALESCE(o.first_name, ''), ' ', COALESCE(o.last_name, '')) AS buyer_name,
        o.email,
        COALESCE(o.country, '') AS destination,
        COALESCE(o.total_value, 0) AS amount,
        COUNT(DISTINCT p.product_id) AS item_count,
        MIN(s.closing_date) AS expected_ship,
        GROUP_CONCAT(DISTINCT u.username ORDER BY u.username SEPARATOR ', ') AS transhippers,
        o.created_at
    FROM orders o
    LEFT JOIN purchases p ON p.order_id = o.order_id
    LEFT JOIN schedule s ON s.schedule_id = p.schedule_id OR s.schedule_id = p.linked_schedule_id
    LEFT JOIN users u ON u.id = s.transhipper_id
    GROUP BY o.order_id
    ORDER BY o.created_at DESC
    LIMIT 500
");
$stmt->execute();
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Orders | BettaBud Admin</title>
resources/style.css

<style>
#pdfModal {
    display:none;
    position:fixed;
    top:0; left:0;
    width:100%; height:100%;
    background:rgba(0,0,0,0.9);
    z-index:9999;
}
#pdfFrame {
    margin: 50px;
    width:75%;
    height:90%;
    border:none;
    
    display: flex;
    justify-content: center;  /* horizontal center */
    align-items: center;      /* vertical center */
}
#closeBtn {
    position:absolute;
    top:10px;
    right:10px;
    background:#fff;
    border:none;
    padding:8px 12px;
    cursor:pointer;
}
.label-link {
    font-size:12px;
}
.label-link a {
    margin-right:6px;
}
</style>

<script>
function openPDF(url) {
    document.getElementById('pdfFrame').src = url;
    document.getElementById('pdfModal').style.display = 'block';
}

function closePDF() {
    document.getElementById('pdfModal').style.display = 'none';
    document.getElementById('pdfFrame').src = '';
}
</script>

</head>
<body>

<div id="pdfModal">
    <iframe id="pdfFrame"></iframe>
    <button id="closeBtn" onclick="closePDF()">Close</button>
</div>

<div class="container">
<div class="subcontainer">
<?php require_once('components/nav.php'); ?>

<div class="content">
<h1 class="section-h1">All Orders</h1>

<div class="large-widget" id="myList">
<div class="flex-box">
    <h2>Orders <span style="font-size: 13px; font-weight: 400; color: var(--text-3);">(<?php echo count($orders); ?> total)</span></h2>
</div>

<p style="font-size: 14px; margin-top: 0;">All customer orders across the platform.</p>

<table id="myOrders">
<thead>
<tr>
    <th>Order ID</th>
    <th>Buyer</th>
    <th>Email</th>
    <th>Destination</th>
    <th>Amount (LCL)</th>
    <th>Items</th>
    <th>Transhipper</th>
    <th>Ship Date</th>
    <th>Labels</th>
    <th>Order Date</th>
</tr>
</thead>

<tbody>
<?php if (empty($orders)): ?>
<tr>
    <td colspan="10" style="text-align: center; padding: 32px; color: var(--text-3);">No orders found.</td>
</tr>
<?php else: ?>

<?php foreach ($orders as $order): ?>
<tr>

<td>
<code style="font-size: 12px; background: var(--hover-bg); padding: 2px 6px; border-radius: 4px;">
<?php echo htmlspecialchars($order['order_id']); ?>
</code>
</td>

<td><?php echo htmlspecialchars(trim($order['buyer_name']) ?: '—'); ?></td>

<td style="font-size: 12px;"><?php echo htmlspecialchars($order['email'] ?? '—'); ?></td>

<td><?php echo htmlspecialchars($order['destination'] ?: '—'); ?></td>

<td>$<?php echo number_format((float)$order['amount'], 2); ?></td>

<td style="text-align: center;"><?php echo (int)$order['item_count']; ?></td>

<td style="font-size: 12px;"><?php echo htmlspecialchars($order['transhippers'] ?: '—'); ?></td>

<td>
<?php echo $order['expected_ship'] ? (new DateTime($order['expected_ship']))->format('d M Y') : '—'; ?>
</td>

<td>
<?php
$labels = getLabels($order['order_id']);

if (empty($labels)) {
    echo '—';
} else {
    foreach ($labels as $label) {

        $safeOrder = urlencode($order['order_id']);
        $safeFile = urlencode($label);
        $url = "includes/get_label.php?order={$safeOrder}&file={$safeFile}";
        
        echo "<div class='label-link'>
            <a href='#' onclick=\"openPDF('{$url}'); return false;\">View</a>
            <a href='{$url}' target='_blank'>⇩</a>
        </div>";
    }
}
?>
</td>

<td style="font-size: 12px;">
<?php echo $order['created_at'] ? (new DateTime($order['created_at']))->format('d M Y') : '—'; ?>
</td>

</tr>
<?php endforeach; ?>

<?php endif; ?>
</tbody>
</table>

</div>
</div>
</div>
</div>

</body>
</html>