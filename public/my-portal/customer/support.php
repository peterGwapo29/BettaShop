<?php
/**
 * support.php — Customer Support Request Status Portal
 * ========================================================
 * Customer-facing page allowing customers to check the status and admin
 * response of their submitted refund/DOA requests using their Ticket Reference
 * and Email Address.
 *
 * ALL database queries are performed via public/includes/support.class.php.
 */

require_once __DIR__ . "/../../includes/config.inc.php";
require_once __DIR__ . "/../../includes/dbh.inc.php";
require_once __DIR__ . "/../../includes/support.class.php";

$errorMessage = '';
$ticketRefInput = '';
$emailInput = '';

// Handle Reset / Check Another Request
if (isset($_GET['action']) && $_GET['action'] === 'reset') {
    unset($_SESSION['customer_verified_ticket_ref']);
    unset($_SESSION['customer_verified_ticket_email']);
    header('Location: support.php');
    exit;
}

// Handle Verification Form Submission (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'verify_ticket') {
        $ticketRefInput = trim($_POST['ticket_ref'] ?? '');
        $emailInput     = trim($_POST['email'] ?? '');

        if ($ticketRefInput === '' || $emailInput === '') {
            $errorMessage = 'Please enter both your Ticket Reference and Email Address.';
        } elseif (!filter_var($emailInput, FILTER_VALIDATE_EMAIL)) {
            $errorMessage = 'Please enter a valid email address.';
        } else {
            // Verify BOTH ticket_ref and email match the same database record
            $matchedTicket = support::getTicketByRefAndEmail($pdo, $ticketRefInput, $emailInput);

            if ($matchedTicket) {
                // Store verified ticket in session
                $_SESSION['customer_verified_ticket_ref']   = $matchedTicket['ticket_ref'];
                $_SESSION['customer_verified_ticket_email'] = $matchedTicket['email'];

                header('Location: support.php');
                exit;
            } else {
                $errorMessage = 'Ticket reference or email address is incorrect.';
            }
        }
    }
}

// Check if an active verified ticket session exists
$verifiedTicket = null;
$ticketFiles    = [];
$orderDetails   = null;

if (!empty($_SESSION['customer_verified_ticket_ref']) && !empty($_SESSION['customer_verified_ticket_email'])) {
    $verifiedTicket = support::getTicketByRefAndEmail(
        $pdo,
        $_SESSION['customer_verified_ticket_ref'],
        $_SESSION['customer_verified_ticket_email']
    );

    if ($verifiedTicket) {
        $ticketFiles  = support::getTicketFiles($pdo, (int) $verifiedTicket['ticket_id']);
        $orderDetails = support::getOrderDetails($pdo, $verifiedTicket['order_id']);
    } else {
        // Ticket no longer valid or changed
        unset($_SESSION['customer_verified_ticket_ref']);
        unset($_SESSION['customer_verified_ticket_email']);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title><?php echo $verifiedTicket ? 'Request ' . htmlspecialchars($verifiedTicket['ticket_ref']) . ' | Support Status' : 'Check Support Request | The Betta Shop'; ?></title>

<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

:root {
    --blue-deep:   #0a2540;
    --blue-mid:    #0e4f7a;
    --blue-light:  #1a8fc1;
    --teal:        #00b5a3;
    --teal-light:  #e0f7f5;
    --sand:        #f5f0e8;
    --text:        #1c2b3a;
    --text-2:      #374151;
    --muted:       #5a7080;
    --border:      #ccdde8;
    --border-light:#e5e7eb;
    --error:       #c0392b;
    --error-bg:    #fef2f2;
    --error-border:#fca5a5;
    --warn:        #92400e;
    --warn-bg:     #fdf3e2;
    --warn-border: #d97706;
    
    /* Status Badge & Banner Colors */
    --pending-bg:     #fef3c7;
    --pending-text:   #92400e;
    --pending-border: #fcd34d;
    --approved-bg:    #d1fae5;
    --approved-text:  #065f46;
    --approved-border:#6ee7b7;
    --denied-bg:      #fee2e2;
    --denied-text:    #991b1b;
    --denied-border:  #fca5a5;

    --radius:      10px;
    --radius-sm:   6px;
    --shadow:      0 2px 18px rgba(10,37,64,.09);
    --shadow-lg:   0 10px 25px -3px rgba(10,37,64,.15);
}

body {
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
    background: var(--sand);
    color: var(--text);
    min-height: 100vh;
    display: flex;
    flex-direction: column;
}

/* ── Header ── */
header {
    background: linear-gradient(135deg, var(--blue-deep) 0%, var(--blue-mid) 60%, var(--blue-light) 100%);
    color: #fff;
    padding: 28px 24px 42px;
    text-align: center;
    position: relative;
    overflow: hidden;
}
header::before {
    content: "";
    position: absolute;
    bottom: -1px; left: 0; right: 0;
    height: 32px;
    background: var(--sand);
    clip-path: ellipse(55% 100% at 50% 100%);
}
.header-brand {
    display: flex;
    align-items: center;
    justify-content: center;
    flex-direction: column;
    gap: 6px;
    margin-bottom: 6px;
}
.header-brand img {
    width: 50px;
    height: 50px;
    object-fit: contain;
}
.header-brand h1 {
    font-size: 1.6rem;
    font-weight: 700;
    letter-spacing: -0.3px;
}
header p {
    margin-top: 4px;
    opacity: 0.9;
    font-size: 0.92rem;
}

/* ── Main Container ── */
main {
    flex: 1;
    max-width: 760px;
    width: 100%;
    margin: 28px auto 40px;
    padding: 0 16px;
}

/* ── Card ── */
.card {
    background: #fff;
    border-radius: var(--radius);
    box-shadow: var(--shadow);
    padding: 32px;
    border: 1px solid rgba(204, 221, 232, 0.6);
}
@media (max-width: 540px) {
    .card { padding: 22px 18px; }
}

/* ── Verification Form ── */
.form-title {
    font-size: 1.25rem;
    font-weight: 700;
    color: var(--blue-deep);
    margin-bottom: 6px;
}
.form-subtitle {
    font-size: 0.88rem;
    color: var(--muted);
    margin-bottom: 22px;
    line-height: 1.5;
}

.field {
    margin-bottom: 18px;
}
label {
    display: block;
    font-size: 0.86rem;
    font-weight: 600;
    margin-bottom: 6px;
    color: var(--text);
}
label .req {
    color: var(--error);
    margin-left: 2px;
}
.input-text {
    width: 100%;
    padding: 11px 14px;
    border: 1.5px solid var(--border);
    border-radius: 7px;
    font-size: 0.94rem;
    font-family: inherit;
    color: var(--text);
    background: #fff;
    transition: border-color .15s, box-shadow .15s;
}
.input-text:focus {
    outline: none;
    border-color: var(--blue-light);
    box-shadow: 0 0 0 3px rgba(26,143,193,.15);
}
.input-text.code-input {
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
}

.error-banner {
    background: var(--error-bg);
    border-left: 4px solid var(--error);
    border-radius: 7px;
    padding: 12px 16px;
    font-size: 0.88rem;
    color: var(--error);
    margin-bottom: 20px;
    line-height: 1.5;
    display: flex;
    align-items: center;
    gap: 8px;
}

.btn-submit {
    display: block;
    width: 100%;
    padding: 13px;
    background: linear-gradient(135deg, var(--teal) 0%, var(--blue-light) 100%);
    color: #fff;
    font-size: 0.98rem;
    font-weight: 700;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    letter-spacing: 0.02em;
    transition: opacity .15s, transform .1s;
    box-shadow: 0 3px 12px rgba(0,181,163,.28);
    margin-top: 10px;
}
.btn-submit:hover { opacity: .94; }
.btn-submit:active { transform: scale(.985); }

.form-footer-note {
    text-align: center;
    margin-top: 20px;
    font-size: 0.84rem;
    color: var(--muted);
}
.form-footer-note a {
    color: var(--blue-light);
    font-weight: 600;
    text-decoration: underline;
}

/* ── Verified Ticket View ── */
.ticket-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    flex-wrap: wrap;
    gap: 12px;
    padding-bottom: 18px;
    border-bottom: 1px solid var(--border-light);
    margin-bottom: 20px;
}
.ticket-meta-title h2 {
    font-size: 1.25rem;
    font-weight: 700;
    color: var(--blue-deep);
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}
.ticket-ref-code {
    background: #eef5fb;
    color: var(--blue-mid);
    padding: 3px 8px;
    border-radius: 5px;
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
    font-size: 1.1rem;
    border: 1px solid var(--border);
}
.ticket-meta-sub {
    font-size: 0.82rem;
    color: var(--muted);
    margin-top: 4px;
}
.btn-switch-ticket {
    background: #f3f4f6;
    color: var(--text-2);
    border: 1px solid var(--border);
    padding: 7px 14px;
    border-radius: 6px;
    font-size: 0.82rem;
    font-weight: 600;
    cursor: pointer;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: background .15s;
}
.btn-switch-ticket:hover {
    background: #e5e7eb;
    color: var(--text);
}

/* ── Status Banners ── */
.status-banner {
    border-radius: 8px;
    padding: 18px 20px;
    margin-bottom: 24px;
    border-left: 5px solid;
}
.status-banner.status-pending {
    background: var(--pending-bg);
    border-color: var(--pending-border);
    color: var(--pending-text);
}
.status-banner.status-approved {
    background: var(--approved-bg);
    border-color: var(--approved-border);
    color: var(--approved-text);
}
.status-banner.status-denied {
    background: var(--denied-bg);
    border-color: var(--denied-border);
    color: var(--denied-text);
}

.status-badge {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 0.76rem;
    font-weight: 700;
    letter-spacing: 0.05em;
    text-transform: uppercase;
    margin-bottom: 8px;
}
.status-badge.badge-pending {
    background: #fde68a;
    color: #854d0e;
}
.status-badge.badge-approved {
    background: #a7f3d0;
    color: #064e3b;
}
.status-badge.badge-denied {
    background: #fecaca;
    color: #7f1d1d;
}

.status-banner-title {
    font-size: 1.05rem;
    font-weight: 700;
    margin-bottom: 6px;
}
.status-banner-desc {
    font-size: 0.9rem;
    line-height: 1.55;
    opacity: 0.95;
}
.admin-response-box {
    margin-top: 12px;
    background: rgba(255, 255, 255, 0.7);
    border-radius: 6px;
    padding: 12px 14px;
    font-size: 0.88rem;
    border: 1px solid rgba(0, 0, 0, 0.08);
}
.admin-response-label {
    font-weight: 700;
    font-size: 0.78rem;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    margin-bottom: 4px;
    display: block;
    opacity: 0.85;
}

/* ── Info Sections ── */
.section-heading {
    font-size: 0.76rem;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: var(--blue-light);
    margin: 24px 0 12px;
}
.section-heading:first-of-type {
    margin-top: 0;
}

.info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
    gap: 14px 18px;
    background: #f8fafc;
    border: 1px solid var(--border-light);
    border-radius: 8px;
    padding: 16px 18px;
}
.info-cell {
    display: flex;
    flex-direction: column;
    gap: 3px;
}
.info-label {
    font-size: 0.75rem;
    color: var(--muted);
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.02em;
}
.info-value {
    font-size: 0.92rem;
    color: var(--text);
    font-weight: 500;
    word-break: break-word;
}
.info-value code {
    background: #eef5fb;
    padding: 2px 6px;
    border-radius: 4px;
    font-size: 0.85rem;
    color: var(--blue-deep);
}

.desc-box {
    background: #f8fafc;
    border: 1px solid var(--border-light);
    border-radius: 8px;
    padding: 14px 16px;
    font-size: 0.9rem;
    line-height: 1.6;
    color: var(--text);
    white-space: pre-wrap;
    word-break: break-word;
}

/* ── Evidence Gallery ── */
.evidence-gallery {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(130px, 1fr));
    gap: 12px;
    margin-top: 8px;
}
.evidence-card {
    border: 1px solid var(--border-light);
    border-radius: 6px;
    overflow: hidden;
    background: #fff;
    transition: transform .15s, box-shadow .15s;
    cursor: pointer;
    text-decoration: none;
    color: inherit;
    display: flex;
    flex-direction: column;
}
.evidence-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,.08);
}
.evidence-thumb {
    width: 100%;
    height: 110px;
    object-fit: cover;
    background: #f1f5f9;
    display: block;
}
.evidence-meta {
    padding: 6px 8px;
    font-size: 0.72rem;
    color: var(--muted);
    background: #ffffff;
    border-top: 1px solid var(--border-light);
}
.evidence-name {
    font-weight: 600;
    color: var(--text-2);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    display: block;
}
.empty-evidence {
    padding: 16px;
    text-align: center;
    color: var(--muted);
    background: #f8fafc;
    border-radius: 6px;
    font-size: 0.85rem;
    font-style: italic;
    border: 1px dashed var(--border);
}

/* ── Lightbox ── */
#imageLightbox {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(10, 37, 64, 0.9);
    z-index: 10000;
    justify-content: center;
    align-items: center;
    flex-direction: column;
    padding: 20px;
}
#lightboxImage {
    max-width: 90%;
    max-height: 80vh;
    object-fit: contain;
    border-radius: 6px;
    box-shadow: var(--shadow-lg);
}
#lightboxCaption {
    color: #fff;
    margin-top: 12px;
    font-size: 0.88rem;
    text-align: center;
}
#lightboxClose {
    position: absolute;
    top: 20px;
    right: 24px;
    background: #fff;
    color: var(--blue-deep);
    border: none;
    padding: 7px 14px;
    font-size: 0.85rem;
    font-weight: 700;
    border-radius: 6px;
    cursor: pointer;
}

/* ── Footer ── */
footer {
    text-align: center;
    padding: 24px 16px 32px;
    font-size: 0.8rem;
    color: var(--muted);
    margin-top: auto;
}
footer a {
    color: var(--blue-light);
    text-decoration: none;
}
footer a:hover {
    text-decoration: underline;
}
</style>
</head>
<body>

<header>
    <div class="header-brand">
        <img src="../../images/logo.webp" alt="BettaBud Logo" onerror="this.src='../../images/logo.webp';">
        <h1>The Betta Shop</h1>
    </div>
    <p>Customer Support &amp; Refund Request Portal</p>
</header>

<main>
<?php if (!$verifiedTicket): ?>

    <!-- ── Stage: Verification Form ── -->
    <div class="card">
        <h2 class="form-title">Check Your Support Request</h2>
        <p class="form-subtitle">
            Enter your Ticket Reference and the email address used when submitting your claim to view its current status and response.
        </p>

        <?php if (!empty($errorMessage)): ?>
            <div class="error-banner" role="alert">
                <span>⚠️</span>
                <span><?php echo htmlspecialchars($errorMessage); ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" action="support.php" novalidate>
            <input type="hidden" name="action" value="verify_ticket" />

            <div class="field">
                <label for="ticket_ref">Ticket Reference <span class="req">*</span></label>
                <input
                    type="text"
                    id="ticket_ref"
                    name="ticket_ref"
                    class="input-text code-input"
                    placeholder="Enter your ticket reference"
                    value="<?php echo htmlspecialchars($ticketRefInput); ?>"
                    required
                    autocomplete="off"
                    autofocus
                />
            </div>

            <div class="field">
                <label for="email">Customer Email <span class="req">*</span></label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    class="input-text"
                    placeholder="jane@example.com"
                    value="<?php echo htmlspecialchars($emailInput); ?>"
                    required
                    autocomplete="email"
                />
            </div>

            <button type="submit" class="btn-submit">View Support Request</button>
        </form>

        <div class="form-footer-note">
            Need to submit a new DOA claim?
            <a href="../../support/index.php">Submit a Refund Request</a>
        </div>
    </div>

<?php else: ?>

    <?php
    $status = strtolower($verifiedTicket['status'] ?? 'pending');
    $statusClass = in_array($status, ['approved', 'denied'], true) ? $status : 'pending';
    $statusLabel = strtoupper($status);
    $adminResponse = trim($verifiedTicket['admin_response'] ?? '');
    ?>

    <!-- ── Stage: Verified Support Request Details (Read-Only) ── -->
    <div class="card">
        
        <!-- Header -->
        <div class="ticket-header">
            <div class="ticket-meta-title">
                <h2>
                    <span>Support Request</span>
                    <span class="ticket-ref-code"><?php echo htmlspecialchars($verifiedTicket['ticket_ref']); ?></span>
                </h2>
                <p class="ticket-meta-sub">
                    Submitted on <?php echo date('M d, Y \a\t g:i A', strtotime($verifiedTicket['created_at'])); ?>
                </p>
            </div>
            <a href="support.php?action=reset" class="btn-switch-ticket">
                🔍 Check Another Request
            </a>
        </div>

        <!-- Status & Response Banner -->
        <div class="status-banner status-<?php echo $statusClass; ?>">
            <span class="status-badge badge-<?php echo $statusClass; ?>">
                ● Status: <?php echo $statusLabel; ?>
            </span>

            <?php if ($status === 'pending'): ?>
                <div class="status-banner-title">Your request is currently being reviewed.</div>
                <div class="status-banner-desc">
                    Our team (Jaylyn) has received your DOA claim and is reviewing your order details and evidence. Please allow 1–2 business days for a response.
                </div>
            <?php elseif ($status === 'approved'): ?>
                <div class="status-banner-title">Your refund request has been approved.</div>
                <div class="status-banner-desc">
                    Your claim has been accepted according to our DOA Policy.
                </div>
                <?php if ($adminResponse !== ''): ?>
                    <div class="admin-response-box">
                        <span class="admin-response-label">Response from Jaylyn</span>
                        <?php echo nl2br(htmlspecialchars($adminResponse)); ?>
                    </div>
                <?php endif; ?>
            <?php elseif ($status === 'denied'): ?>
                <div class="status-banner-title">Your refund request has been denied.</div>
                <div class="status-banner-desc">
                    Your claim has been reviewed and could not be approved.
                </div>
                <?php if ($adminResponse !== ''): ?>
                    <div class="admin-response-box">
                        <span class="admin-response-label">Reason for Denial</span>
                        <?php echo nl2br(htmlspecialchars($adminResponse)); ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <!-- Request Details -->
        <h3 class="section-heading">Request Information</h3>
        <div class="info-grid">
            <div class="info-cell">
                <span class="info-label">Customer Name</span>
                <span class="info-value"><?php echo htmlspecialchars(trim($verifiedTicket['first_name'] . ' ' . $verifiedTicket['last_name']) ?: '—'); ?></span>
            </div>
            <div class="info-cell">
                <span class="info-label">Email Address</span>
                <span class="info-value"><?php echo htmlspecialchars($verifiedTicket['email']); ?></span>
            </div>
            <div class="info-cell">
                <span class="info-label">Order ID</span>
                <span class="info-value">
                    <code><?php echo htmlspecialchars($verifiedTicket['order_id']); ?></code>
                    <?php if ($orderDetails && !empty($orderDetails['order_date'])): ?>
                        <span style="font-size: 0.78rem; color: var(--muted);">(<?php echo htmlspecialchars($orderDetails['order_date']); ?>)</span>
                    <?php endif; ?>
                </span>
            </div>
            <div class="info-cell">
                <span class="info-label">Delivery Date</span>
                <span class="info-value"><?php echo date('M d, Y', strtotime($verifiedTicket['delivery_date'])); ?></span>
            </div>
            <div class="info-cell">
                <span class="info-label">Fish SKU(s)</span>
                <span class="info-value"><?php echo htmlspecialchars($verifiedTicket['fish_sku'] ?: ($orderDetails['sku'] ?? '—')); ?></span>
            </div>
            <div class="info-cell">
                <span class="info-label">Number of DOA Fish</span>
                <span class="info-value"><?php echo (int) $verifiedTicket['doa_count']; ?></span>
            </div>
            <div class="info-cell">
                <span class="info-label">Requested Resolution</span>
                <span class="info-value">
                    <?php
                    $res = $verifiedTicket['resolution'];
                    if ($res === 'store-credit') echo 'Store credit';
                    elseif ($res === 'replacement') echo 'Replacement fish';
                    elseif ($res === 'other') echo 'Other: ' . htmlspecialchars($verifiedTicket['resolution_other'] ?? '');
                    else echo htmlspecialchars($res);
                    ?>
                </span>
            </div>
            <div class="info-cell">
                <span class="info-label">Last Updated</span>
                <span class="info-value"><?php echo date('M d, Y \a\t g:i A', strtotime($verifiedTicket['modified_at'] ?: $verifiedTicket['created_at'])); ?></span>
            </div>
        </div>

        <!-- Description -->
        <h3 class="section-heading">Observation Notes / Description</h3>
        <div class="desc-box">
            <?php echo nl2br(htmlspecialchars($verifiedTicket['description'])); ?>
        </div>

        <!-- Uploaded Evidence -->
        <h3 class="section-heading">Uploaded Evidence Photos (<?php echo count($ticketFiles); ?>)</h3>
        <?php if (!empty($ticketFiles)): ?>
            <div class="evidence-gallery">
                <?php foreach ($ticketFiles as $file): ?>
                    <?php
                    $filePath = __DIR__ . "/../../uploads/tickets/{$verifiedTicket['ticket_id']}/{$file['stored_filename']}";
                    $fileUrl  = "../../uploads/tickets/{$verifiedTicket['ticket_id']}/" . rawurlencode($file['stored_filename']);
                    $fileExists = file_exists($filePath);
                    $fileSizeFormatted = round($file['size_bytes'] / 1024, 1) . ' KB';
                    ?>
                    <?php if ($fileExists): ?>
                        <div class="evidence-card" onclick="openLightbox('<?php echo htmlspecialchars($fileUrl); ?>', '<?php echo htmlspecialchars(addslashes($file['original_filename'])); ?>')">
                            <img src="<?php echo htmlspecialchars($fileUrl); ?>" alt="<?php echo htmlspecialchars($file['original_filename']); ?>" class="evidence-thumb" loading="lazy" />
                            <div class="evidence-meta">
                                <span class="evidence-name" title="<?php echo htmlspecialchars($file['original_filename']); ?>">
                                    <?php echo htmlspecialchars($file['original_filename']); ?>
                                </span>
                                <span><?php echo $fileSizeFormatted; ?></span>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="evidence-card" style="cursor: default; opacity: 0.7;">
                            <div style="height: 110px; display: flex; align-items: center; justify-content: center; background: #f1f5f9; color: var(--muted); font-size: 0.8rem;">
                                📷 File
                            </div>
                            <div class="evidence-meta">
                                <span class="evidence-name"><?php echo htmlspecialchars($file['original_filename']); ?></span>
                                <span><?php echo $fileSizeFormatted; ?></span>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-evidence">
                No evidence photos were attached to this request.
            </div>
        <?php endif; ?>

        <div class="form-footer-note" style="margin-top: 28px;">
            Need to submit a different claim?
            <a href="../../support/index.php">Submit a New Refund Request</a>
        </div>

    </div>

    <!-- Lightbox Modal -->
    <div id="imageLightbox" onclick="closeLightbox(event)">
        <button id="lightboxClose" onclick="closeLightbox(event)">✕ Close</button>
        <img id="lightboxImage" src="" alt="Evidence Fullscreen" />
        <div id="lightboxCaption"></div>
    </div>

    <script>
    function openLightbox(url, caption) {
        var box = document.getElementById('imageLightbox');
        var img = document.getElementById('lightboxImage');
        var cap = document.getElementById('lightboxCaption');
        if (!box || !img) return;

        img.src = url;
        cap.textContent = caption || '';
        box.style.display = 'flex';
    }

    function closeLightbox(event) {
        if (event.target.id === 'imageLightbox' || event.target.id === 'lightboxClose') {
            var box = document.getElementById('imageLightbox');
            var img = document.getElementById('lightboxImage');
            if (box) box.style.display = 'none';
            if (img) img.src = '';
        }
    }

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            var box = document.getElementById('imageLightbox');
            if (box && box.style.display === 'flex') {
                box.style.display = 'none';
            }
        }
    });
    </script>

<?php endif; ?>
</main>

<footer>
    Need help? Email us at <a href="mailto:support@bettabud.com">support@bettabud.com</a> · <a href="../../support/index.php">Submit a Claim</a>
</footer>

</body>
</html>
