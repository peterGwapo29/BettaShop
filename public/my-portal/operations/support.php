<?php
/**
 * support.php — Admin Support / Refund Request Management
 * =========================================================
 * Admin portal for reviewing, inspecting, approving, and denying customer
 * refund/DOA requests with photo evidence, order data, and DataTables integration.
 *
 * ALL database operations are routed through public/includes/support.class.php.
 */

require_once "../../includes/dbh.inc.php";
require_once "../../includes/config.inc.php";
require_once "../../includes/support.class.php";

// Admin authentication / access-control system
if (file_exists("includes/ValidateUser.inc.php")) {
    require_once "includes/ValidateUser.inc.php";
} elseif (file_exists("../includes/ValidateUser.inc.php")) {
    require_once "../includes/ValidateUser.inc.php";
} elseif (file_exists("../../includes/ValidateUser.inc.php")) {
    require_once "../../includes/ValidateUser.inc.php";
}
if (function_exists('validateUserCredentials')) {
    validateUserCredentials();
}

// -----------------------------------------------------------------------------
// AJAX API HANDLERS
// -----------------------------------------------------------------------------

// Handle AJAX POST actions (Approve / Deny)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    $rawInput = file_get_contents('php://input');
    $postData = [];
    if (!empty($rawInput)) {
        $decoded = json_decode($rawInput, true);
        if (is_array($decoded)) {
            $postData = $decoded;
        }
    }
    if (empty($postData)) {
        $postData = $_POST;
    }

    $action        = trim($postData['action'] ?? '');
    $ticketId      = (int) ($postData['ticket_id'] ?? 0);
    $adminResponse = trim($postData['admin_response'] ?? '');

    if ($ticketId <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid ticket ID.']);
        exit;
    }

    $ticket = support::getTicketById($pdo, $ticketId);
    if (!$ticket) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Ticket not found.']);
        exit;
    }

    if ($action === 'approve') {
        if ($ticket['status'] !== 'pending') {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => "Ticket {$ticket['ticket_ref']} is already {$ticket['status']} and cannot be approved again.",
            ]);
            exit;
        }

        $success = support::approveTicket($pdo, $ticketId);
        if ($success) {
            echo json_encode([
                'success'    => true,
                'message'    => "Request {$ticket['ticket_ref']} has been approved successfully.",
                'ticket_id'  => $ticketId,
                'ticket_ref' => $ticket['ticket_ref'],
                'status'     => 'approved',
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to update ticket status in database.']);
        }
        exit;
    }

    if ($action === 'deny') {
        if ($ticket['status'] !== 'pending') {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => "Ticket {$ticket['ticket_ref']} is already {$ticket['status']} and cannot be modified.",
            ]);
            exit;
        }

        if ($adminResponse === '') {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Please provide an admin response/reason for denying this request.']);
            exit;
        }

        $success = support::denyTicket($pdo, $ticketId, $adminResponse);
        if ($success) {
            echo json_encode([
                'success'        => true,
                'message'        => "Request {$ticket['ticket_ref']} has been denied.",
                'ticket_id'      => $ticketId,
                'ticket_ref'     => $ticket['ticket_ref'],
                'status'         => 'denied',
                'admin_response' => $adminResponse,
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to deny ticket in database.']);
        }
        exit;
    }

    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid action specified.']);
    exit;
}

// Handle AJAX GET action (Get full ticket details + files + order)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get_details') {
    header('Content-Type: application/json');

    $ticketId = (int) ($_GET['ticket_id'] ?? 0);
    if ($ticketId <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid ticket ID.']);
        exit;
    }

    $ticket = support::getTicketById($pdo, $ticketId);
    if (!$ticket) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Ticket not found.']);
        exit;
    }

    $files = support::getTicketFiles($pdo, $ticketId);
    $formattedFiles = [];
    foreach ($files as $file) {
        $diskPath = __DIR__ . "/../../uploads/tickets/{$ticketId}/{$file['stored_filename']}";
        $fileExists = file_exists($diskPath);
        $fileUrl = $fileExists ? "../../uploads/tickets/{$ticketId}/" . rawurlencode($file['stored_filename']) : '';

        $formattedFiles[] = [
            'file_id'           => (int) $file['file_id'],
            'original_filename' => $file['original_filename'],
            'stored_filename'   => $file['stored_filename'],
            'mime_type'         => $file['mime_type'],
            'size_bytes'        => (int) $file['size_bytes'],
            'size_formatted'    => round($file['size_bytes'] / 1024, 1) . ' KB',
            'exists'            => $fileExists,
            'url'               => $fileUrl,
            'created_at'        => $file['created_at'],
        ];
    }

    $order = support::getOrderDetails($pdo, $ticket['order_id']);

    echo json_encode([
        'success' => true,
        'ticket'  => $ticket,
        'files'   => $formattedFiles,
        'order'   => $order,
    ]);
    exit;
}

// -----------------------------------------------------------------------------
// MAIN PAGE RENDERING
// -----------------------------------------------------------------------------

// Retrieve all real tickets from database ordered newest first
$tickets = support::getTickets($pdo, 500);

$totalCount    = count($tickets);
$pendingCount  = 0;
$approvedCount = 0;
$deniedCount   = 0;

foreach ($tickets as $t) {
    $st = strtolower($t['status'] ?? 'pending');
    if ($st === 'pending') $pendingCount++;
    elseif ($st === 'approved') $approvedCount++;
    elseif ($st === 'denied') $deniedCount++;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Support & Refund Requests | BettaBud Admin</title>

<!-- DataTables CSS -->
<link rel="stylesheet" href="https://cdn.datatables.net/2.1.8/css/dataTables.dataTables.min.css">

<style>
:root {
    --text-1: #111827;
    --text-2: #374151;
    --text-3: #6b7280;
    --hover-bg: #f3f4f6;
    --border: #e5e7eb;
    --border-dark: #d1d5db;
    --card-bg: #ffffff;
    --bg-main: #f9fafb;
    --primary: #0a2540;
    --primary-light: #1a8fc1;
    
    /* Status Badge Colors */
    --pending-bg: #fef3c7;
    --pending-text: #92400e;
    --pending-border: #fcd34d;
    --approved-bg: #d1fae5;
    --approved-text: #065f46;
    --approved-border: #6ee7b7;
    --denied-bg: #fee2e2;
    --denied-text: #991b1b;
    --denied-border: #fca5a5;
    
    --radius: 6px;
    --radius-lg: 10px;
    --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
    --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
    --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
}

* { box-sizing: border-box; }

body {
    margin: 0;
    padding: 0;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
    background-color: var(--bg-main);
    color: var(--text-1);
    font-size: 14px;
    line-height: 1.5;
}

.container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 20px 24px 40px;
}

.subcontainer {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.content {
    background: transparent;
}

.section-h1 {
    font-size: 24px;
    font-weight: 700;
    color: var(--primary);
    margin: 0 0 16px 0;
}

.large-widget {
    background: var(--card-bg);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow-sm);
    padding: 24px;
    margin-bottom: 24px;
}

.flex-box {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 16px;
    margin-bottom: 16px;
}

.flex-box h2 {
    font-size: 18px;
    font-weight: 600;
    margin: 0;
    color: var(--text-1);
}

/* Filter Pills */
.filter-bar {
    display: flex;
    gap: 8px;
    align-items: center;
    flex-wrap: wrap;
}

.filter-btn {
    background: var(--hover-bg);
    border: 1px solid var(--border);
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: 500;
    color: var(--text-2);
    cursor: pointer;
    transition: all 0.2s;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.filter-btn:hover {
    background: #e5e7eb;
    color: var(--text-1);
}

.filter-btn.active {
    background: var(--primary);
    border-color: var(--primary);
    color: #ffffff;
}

.filter-count {
    background: rgba(0, 0, 0, 0.08);
    padding: 1px 6px;
    border-radius: 10px;
    font-size: 11px;
}

.filter-btn.active .filter-count {
    background: rgba(255, 255, 255, 0.25);
    color: #ffffff;
}

/* Table Responsive Wrapper */
.table-responsive {
    width: 100%;
    overflow-x: auto;
    margin-top: 14px;
}

/* DataTables Overrides matching BettaBud design */
div.dt-container {
    font-size: 13px;
    color: var(--text-2);
}

div.dt-container .dt-length,
div.dt-container .dt-search {
    margin-bottom: 14px;
}

div.dt-container .dt-search input {
    border: 1px solid var(--border-dark);
    border-radius: 6px;
    padding: 6px 12px;
    margin-left: 6px;
    font-size: 13px;
    outline: none;
    background: #ffffff;
    transition: border-color 0.15s;
}

div.dt-container .dt-search input:focus {
    border-color: var(--primary-light);
    box-shadow: 0 0 0 2px rgba(26, 143, 193, 0.15);
}

div.dt-container .dt-length select {
    border: 1px solid var(--border-dark);
    border-radius: 6px;
    padding: 5px 10px;
    margin: 0 4px;
    font-size: 13px;
    outline: none;
    background: #ffffff;
}

div.dt-container .dt-info {
    font-size: 12px;
    color: var(--text-3);
    padding-top: 14px;
}

div.dt-container .dt-paging {
    padding-top: 12px;
}

div.dt-container .dt-paging .dt-paging-button {
    border: 1px solid var(--border) !important;
    background: #ffffff !important;
    color: var(--text-2) !important;
    border-radius: 4px !important;
    padding: 4px 10px !important;
    font-size: 12px !important;
    font-weight: 500 !important;
    margin: 0 2px !important;
    transition: all 0.15s !important;
}

div.dt-container .dt-paging .dt-paging-button:hover {
    background: var(--hover-bg) !important;
    color: var(--text-1) !important;
    border-color: var(--border-dark) !important;
}

div.dt-container .dt-paging .dt-paging-button.current,
div.dt-container .dt-paging .dt-paging-button.current:hover {
    background: var(--primary) !important;
    color: #ffffff !important;
    border-color: var(--primary) !important;
}

div.dt-container .dt-paging .dt-paging-button.disabled,
div.dt-container .dt-paging .dt-paging-button.disabled:hover {
    opacity: 0.45 !important;
    background: #ffffff !important;
    color: var(--text-3) !important;
    cursor: not-allowed !important;
}

/* Base Table Styling */
table.dataTable {
    width: 100% !important;
    border-collapse: collapse !important;
    text-align: left;
    background: var(--card-bg);
    border: 1px solid var(--border) !important;
    border-radius: var(--radius);
    margin: 0 !important;
}

table.dataTable thead th {
    background: #f8fafc !important;
    color: var(--text-3) !important;
    font-size: 12px !important;
    font-weight: 600 !important;
    text-transform: uppercase !important;
    letter-spacing: 0.05em !important;
    padding: 12px 14px !important;
    border-bottom: 1px solid var(--border) !important;
    white-space: nowrap !important;
}

table.dataTable tbody td {
    padding: 12px 14px !important;
    border-bottom: 1px solid var(--border) !important;
    vertical-align: middle !important;
    font-size: 13px !important;
    color: var(--text-2) !important;
}

table.dataTable tbody tr:hover {
    background: #fafafa !important;
}

/* Code Tags & Badges */
code {
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
    font-size: 12px;
    background: var(--hover-bg);
    padding: 2px 6px;
    border-radius: 4px;
    color: var(--text-1);
}

.badge {
    display: inline-flex;
    align-items: center;
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 600;
    text-transform: capitalize;
    border: 1px solid transparent;
}

.badge-pending {
    background: var(--pending-bg);
    color: var(--pending-text);
    border-color: var(--pending-border);
}

.badge-approved {
    background: var(--approved-bg);
    color: var(--approved-text);
    border-color: var(--approved-border);
}

.badge-denied {
    background: var(--denied-bg);
    color: var(--denied-text);
    border-color: var(--denied-border);
}

/* Action Buttons */
.btn-group {
    display: inline-flex;
    gap: 6px;
    align-items: center;
}

.btn {
    border: none;
    border-radius: 4px;
    padding: 5px 10px;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.15s ease-in-out;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    text-decoration: none;
}

.btn-view {
    background: #e0f2fe;
    color: #0369a1;
    border: 1px solid #bae6fd;
}
.btn-view:hover {
    background: #bae6fd;
    color: #0284c7;
}

.btn-approve {
    background: #dcfce7;
    color: #15803d;
    border: 1px solid #bbf7d0;
}
.btn-approve:hover {
    background: #bbf7d0;
    color: #166534;
}

.btn-deny {
    background: #fee2e2;
    color: #b91c1c;
    border: 1px solid #fecaca;
}
.btn-deny:hover {
    background: #fecaca;
    color: #991b1b;
}

.btn-secondary {
    background: #f3f4f6;
    color: var(--text-2);
    border: 1px solid var(--border);
}
.btn-secondary:hover {
    background: #e5e7eb;
    color: var(--text-1);
}

/* Toast Notification */
#toastNotification {
    position: fixed;
    bottom: 24px;
    right: 24px;
    padding: 12px 18px;
    background: #1e293b;
    color: #ffffff;
    border-radius: var(--radius);
    box-shadow: var(--shadow-lg);
    font-size: 13px;
    font-weight: 500;
    z-index: 10000;
    display: none;
    align-items: center;
    gap: 8px;
    max-width: 400px;
}
#toastNotification.toast-success {
    background: #065f46;
    border-left: 4px solid #34d399;
}
#toastNotification.toast-error {
    background: #991b1b;
    border-left: 4px solid #f87171;
}

/* Modal Dialog */
.modal-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.65);
    z-index: 9999;
    overflow-y: auto;
    padding: 20px 16px;
}

.modal-dialog {
    background: #ffffff;
    width: 100%;
    max-width: 760px;
    margin: 24px auto;
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow-lg);
    border: 1px solid var(--border);
    overflow: hidden;
}

.modal-header {
    background: #f8fafc;
    padding: 16px 20px;
    border-bottom: 1px solid var(--border);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-header h3 {
    margin: 0;
    font-size: 17px;
    font-weight: 700;
    color: var(--primary);
    display: flex;
    align-items: center;
    gap: 8px;
}

.modal-close-btn {
    background: transparent;
    border: none;
    font-size: 22px;
    color: var(--text-3);
    cursor: pointer;
    line-height: 1;
    padding: 2px 6px;
    border-radius: 4px;
}
.modal-close-btn:hover {
    color: var(--text-1);
    background: var(--hover-bg);
}

.modal-body {
    padding: 20px;
    max-height: calc(85vh - 130px);
    overflow-y: auto;
    display: flex;
    flex-direction: column;
    gap: 18px;
}

.modal-footer {
    background: #f8fafc;
    padding: 14px 20px;
    border-top: 1px solid var(--border);
    display: flex;
    justify-content: flex-end;
    align-items: center;
    gap: 10px;
}

.info-section {
    background: #ffffff;
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 16px;
}

.info-section-title {
    font-size: 12px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--text-3);
    margin: 0 0 12px 0;
    padding-bottom: 6px;
    border-bottom: 1px solid var(--border);
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 12px 16px;
}

.info-item {
    display: flex;
    flex-direction: column;
    gap: 3px;
}

.info-label {
    font-size: 11px;
    font-weight: 600;
    color: var(--text-3);
    text-transform: uppercase;
}

.info-value {
    font-size: 13px;
    font-weight: 500;
    color: var(--text-1);
    word-break: break-word;
}

.info-description-box {
    margin-top: 10px;
    padding: 10px 12px;
    background: #f8fafc;
    border: 1px solid var(--border);
    border-radius: 4px;
    font-size: 13px;
    color: var(--text-2);
    white-space: pre-wrap;
    line-height: 1.5;
}

/* Admin Response Box */
.admin-response-box {
    background: #fff5f5;
    border: 1px solid var(--denied-border);
    border-radius: 4px;
    padding: 12px;
}

.admin-response-box strong {
    color: var(--denied-text);
    display: block;
    margin-bottom: 4px;
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.admin-response-text {
    font-size: 13px;
    color: #7f1d1d;
    white-space: pre-wrap;
    line-height: 1.5;
}

/* Evidence Gallery */
.evidence-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(130px, 1fr));
    gap: 12px;
    margin-top: 8px;
}

.evidence-card {
    border: 1px solid var(--border);
    border-radius: var(--radius);
    overflow: hidden;
    background: #f8fafc;
    cursor: pointer;
    transition: all 0.2s;
    text-decoration: none;
    color: inherit;
    display: flex;
    flex-direction: column;
}
.evidence-card:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-md);
    border-color: var(--primary-light);
}

.evidence-thumb {
    width: 100%;
    height: 100px;
    object-fit: cover;
    background: #e2e8f0;
    display: block;
}

.evidence-placeholder {
    width: 100%;
    height: 100px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #f1f5f9;
    color: var(--text-3);
    font-size: 11px;
    text-align: center;
    padding: 8px;
}

.evidence-meta {
    padding: 6px 8px;
    font-size: 11px;
    color: var(--text-3);
    background: #ffffff;
    border-top: 1px solid var(--border);
}

.evidence-filename {
    font-weight: 600;
    color: var(--text-2);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    display: block;
}

.empty-evidence {
    padding: 20px;
    text-align: center;
    color: var(--text-3);
    background: #f8fafc;
    border-radius: 4px;
    font-style: italic;
    border: 1px dashed var(--border);
}

/* Denial Form inside Modal */
#denyFormContainer {
    display: none;
    background: #fff5f5;
    border: 1px solid var(--denied-border);
    border-radius: var(--radius);
    padding: 14px;
}

#denyFormContainer h4 {
    margin: 0 0 6px 0;
    color: var(--denied-text);
    font-size: 13px;
}

#denyReasonInput {
    width: 100%;
    min-height: 70px;
    padding: 8px 10px;
    border: 1px solid var(--border-dark);
    border-radius: 4px;
    font-family: inherit;
    font-size: 13px;
    resize: vertical;
    outline: none;
}
#denyReasonInput:focus {
    border-color: #ef4444;
}

.deny-form-actions {
    display: flex;
    justify-content: flex-end;
    gap: 8px;
    margin-top: 8px;
}

/* Lightbox for Image Zoom */
#imageLightbox {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.9);
    z-index: 10001;
    justify-content: center;
    align-items: center;
    flex-direction: column;
}

#lightboxImage {
    max-width: 90%;
    max-height: 85%;
    object-fit: contain;
    border-radius: 4px;
}

#lightboxCaption {
    color: #ffffff;
    margin-top: 10px;
    font-size: 13px;
    text-align: center;
}

#lightboxClose {
    position: absolute;
    top: 14px;
    right: 18px;
    background: #ffffff;
    color: #111827;
    border: none;
    padding: 6px 12px;
    font-size: 13px;
    font-weight: 600;
    border-radius: 4px;
    cursor: pointer;
}
</style>
</head>
<body>

<!-- Toast Notification -->
<div id="toastNotification"></div>

<!-- Image Lightbox -->
<div id="imageLightbox" onclick="closeLightbox(event)">
    <button id="lightboxClose" onclick="closeLightbox(event)">✕ Close</button>
    <img id="lightboxImage" src="" alt="Evidence Fullscreen">
    <div id="lightboxCaption"></div>
</div>

<!-- Ticket Details Modal -->
<div class="modal-overlay" id="ticketModal" onclick="handleModalOverlayClick(event)">
    <div class="modal-dialog">
        <div class="modal-header">
            <h3>
                <span>Ticket Details</span>
                <code id="modalTicketRef">DOA-XXXXXX</code>
            </h3>
            <button class="modal-close-btn" onclick="closeTicketModal()">&times;</button>
        </div>

        <div class="modal-body" id="modalBodyContent">
            <!-- Loading indicator -->
            <div id="modalLoading" style="text-align:center; padding: 36px; color: var(--text-3);">
                Loading ticket details...
            </div>

            <!-- Dynamic Data Container -->
            <div id="modalDataContainer" style="display:none; flex-direction:column; gap:16px;">
                
                <!-- Section 1: Customer & Request Details -->
                <div class="info-section">
                    <div class="info-section-title">
                        <span>Customer & Request Details</span>
                        <span id="modalStatusBadge" class="badge">Pending</span>
                    </div>

                    <div class="info-grid">
                        <div class="info-item">
                            <span class="info-label">Customer</span>
                            <span class="info-value" id="modalCustomerName">—</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Email</span>
                            <span class="info-value" id="modalCustomerEmail">—</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Order ID</span>
                            <span class="info-value"><code id="modalOrderId">—</code></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Delivery Date</span>
                            <span class="info-value" id="modalDeliveryDate">—</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Fish SKU</span>
                            <span class="info-value" id="modalFishSku">—</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">DOA Count</span>
                            <span class="info-value" id="modalDoaCount">—</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Requested Resolution</span>
                            <span class="info-value" id="modalResolution">—</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Date Submitted</span>
                            <span class="info-value" id="modalCreatedAt">—</span>
                        </div>
                    </div>

                    <div style="margin-top: 12px;">
                        <span class="info-label">Customer Description</span>
                        <div class="info-description-box" id="modalDescription">No description provided.</div>
                    </div>

                    <!-- Admin response (if denied) -->
                    <div id="modalAdminResponseContainer" style="display:none; margin-top: 12px;">
                        <div class="admin-response-box">
                            <strong>Admin Response / Denial Reason:</strong>
                            <div class="admin-response-text" id="modalAdminResponseText"></div>
                        </div>
                    </div>
                </div>

                <!-- Section 2: Related Order Info -->
                <div class="info-section">
                    <div class="info-section-title">Related Order Information</div>
                    <div id="modalOrderFound" class="info-grid" style="display:none;">
                        <div class="info-item">
                            <span class="info-label">Order ID</span>
                            <span class="info-value"><code id="modalRelOrderId">—</code></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Buyer Name</span>
                            <span class="info-value" id="modalRelBuyerName">—</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Order Email</span>
                            <span class="info-value" id="modalRelEmail">—</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Destination</span>
                            <span class="info-value" id="modalRelCountry">—</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Total Amount</span>
                            <span class="info-value" id="modalRelAmount">—</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Order Date</span>
                            <span class="info-value" id="modalRelDate">—</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Order SKU</span>
                            <span class="info-value"><code id="modalRelSku">—</code></span>
                        </div>
                    </div>
                    <div id="modalOrderNotFound" class="empty-evidence" style="display:none;">
                        No matching record found in orders table.
                    </div>
                </div>

                <!-- Section 3: Uploaded Evidence -->
                <div class="info-section">
                    <div class="info-section-title">
                        <span>Uploaded Evidence</span>
                        <span id="modalEvidenceCount" style="font-size: 11px; font-weight: 400; color: var(--text-3);">0 files</span>
                    </div>
                    <div id="modalEvidenceContainer"></div>
                </div>

                <!-- Denial Form (toggled when Deny is clicked) -->
                <div id="denyFormContainer">
                    <h4>Deny Refund Request</h4>
                    <p style="margin: 0 0 8px 0; font-size: 12px; color: var(--denied-text);">
                        Please provide a reason for denying this request.
                    </p>
                    <textarea id="denyReasonInput" placeholder="Enter reason for denial..."></textarea>
                    <div class="deny-form-actions">
                        <button class="btn btn-secondary" onclick="cancelDenyForm()">Cancel</button>
                        <button class="btn btn-deny" onclick="submitDenial()">Confirm Denial</button>
                    </div>
                </div>

            </div>
        </div>

        <div class="modal-footer" id="modalFooter">
            <button class="btn btn-secondary" onclick="closeTicketModal()">Close</button>
            <div id="modalActionButtons" class="btn-group" style="display:none;">
                <button class="btn btn-approve" id="modalApproveBtn" onclick="triggerModalApprove()">✓ Approve</button>
                <button class="btn btn-deny" id="modalDenyBtn" onclick="toggleDenyForm()">✕ Deny</button>
            </div>
        </div>
    </div>
</div>

<div class="container">
    <div class="subcontainer">
        
        <?php
        if (file_exists('components/nav.php')) {
            require_once('components/nav.php');
        } elseif (file_exists('../components/nav.php')) {
            require_once('../components/nav.php');
        }
        ?>

        <div class="content">
            <h1 class="section-h1">Support & Refund Requests</h1>

            <div class="large-widget" id="myList">
                <div class="flex-box">
                    <div>
                        <h2>Refund Requests <span style="font-size: 13px; font-weight: 400; color: var(--text-3);">(<?php echo $totalCount; ?> total)</span></h2>
                        <p style="font-size: 13px; margin: 4px 0 0 0; color: var(--text-3);">
                            Review customer refund / DOA requests, inspect evidence photos, and approve or deny requests.
                        </p>
                    </div>

                    <!-- Filter pills -->
                    <div class="filter-bar">
                        <button class="filter-btn active" data-filter="all" onclick="filterTable('all', this)">
                            All <span class="filter-count"><?php echo $totalCount; ?></span>
                        </button>
                        <button class="filter-btn" data-filter="pending" onclick="filterTable('pending', this)">
                            Pending <span class="filter-count" id="badgePendingCount"><?php echo $pendingCount; ?></span>
                        </button>
                        <button class="filter-btn" data-filter="approved" onclick="filterTable('approved', this)">
                            Approved <span class="filter-count" id="badgeApprovedCount"><?php echo $approvedCount; ?></span>
                        </button>
                        <button class="filter-btn" data-filter="denied" onclick="filterTable('denied', this)">
                            Denied <span class="filter-count" id="badgeDeniedCount"><?php echo $deniedCount; ?></span>
                        </button>
                    </div>
                </div>

                <div class="table-responsive">
                    <table id="myOrders" class="display" style="width:100%">
                        <thead>
                            <tr>
                                <th>Ticket Ref</th>
                                <th>Customer</th>
                                <th>Email</th>
                                <th>Order ID</th>
                                <th>Delivery Date</th>
                                <th style="text-align: center;">DOA Count</th>
                                <th>Resolution</th>
                                <th>Status</th>
                                <th>Created Date</th>
                                <th style="text-align: center;">Action</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php foreach ($tickets as $ticket): 
                                $ticketId     = (int) $ticket['ticket_id'];
                                $ticketRef    = htmlspecialchars($ticket['ticket_ref']);
                                $customerName = trim($ticket['first_name'] . ' ' . $ticket['last_name']);
                                $email        = htmlspecialchars($ticket['email']);
                                $orderId      = htmlspecialchars($ticket['order_id']);
                                $deliveryDate = $ticket['delivery_date'] ? (new DateTime($ticket['delivery_date']))->format('d M Y') : '—';
                                $doaCount     = (int) $ticket['doa_count'];
                                $status       = strtolower($ticket['status'] ?? 'pending');

                                $resolution = htmlspecialchars(ucwords(str_replace('-', ' ', $ticket['resolution'])));
                                if ($ticket['resolution'] === 'other' && !empty($ticket['resolution_other'])) {
                                    $resolution .= ': ' . htmlspecialchars($ticket['resolution_other']);
                                }

                                $dateSubmitted = $ticket['created_at'] ? (new DateTime($ticket['created_at']))->format('d M Y') : '—';
                            ?>
                            <tr id="row-ticket-<?php echo $ticketId; ?>" data-status="<?php echo $status; ?>">
                                
                                <td>
                                    <code><?php echo $ticketRef; ?></code>
                                </td>

                                <td style="font-weight: 500;">
                                    <?php echo htmlspecialchars($customerName ?: '—'); ?>
                                </td>

                                <td style="font-size: 12px;">
                                    <?php echo $email; ?>
                                </td>

                                <td>
                                    <code><?php echo $orderId; ?></code>
                                </td>

                                <td>
                                    <?php echo $deliveryDate; ?>
                                </td>

                                <td style="text-align: center; font-weight: 600;">
                                    <?php echo $doaCount; ?>
                                </td>

                                <td style="font-size: 12px;">
                                    <?php echo $resolution; ?>
                                </td>

                                <td>
                                    <span id="badge-ticket-<?php echo $ticketId; ?>" class="badge badge-<?php echo $status; ?>">
                                        <?php echo ucfirst($status); ?>
                                    </span>
                                </td>

                                <td style="font-size: 12px; color: var(--text-3);">
                                    <?php echo $dateSubmitted; ?>
                                </td>

                                <td style="text-align: center;">
                                    <div class="btn-group" id="actions-ticket-<?php echo $ticketId; ?>">
                                        <button class="btn btn-view" onclick="openTicketModal(<?php echo $ticketId; ?>)">View</button>
                                        
                                        <?php if ($status === 'pending'): ?>
                                        <button class="btn btn-approve" onclick="confirmApprove(<?php echo $ticketId; ?>, '<?php echo $ticketRef; ?>')">Approve</button>
                                        <button class="btn btn-deny" onclick="openDenyModal(<?php echo $ticketId; ?>, '<?php echo $ticketRef; ?>')">Deny</button>
                                        <?php endif; ?>
                                    </div>
                                </td>

                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

            </div>
        </div>

    </div>
</div>

<!-- jQuery & DataTables JS -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/2.1.8/js/dataTables.min.js"></script>

<script>
let currentActiveTicket = null;
let dataTable = null;

$(document).ready(function() {
    dataTable = $('#myOrders').DataTable({
        pageLength: 10,
        lengthMenu: [5, 10, 25, 50, 100],
        order: [[8, 'desc']], // Sort by Created Date descending by default
        columnDefs: [
            { orderable: false, searchable: false, targets: 9 }, // Action column
            { className: 'dt-center', targets: [5, 9] }
        ],
        language: {
            search: "",
            searchPlaceholder: "Search requests...",
            lengthMenu: "Show _MENU_ entries",
            info: "Showing _START_ to _END_ of _TOTAL_ requests",
            infoEmpty: "Showing 0 to 0 of 0 requests",
            infoFiltered: "(filtered from _MAX_ total)",
            emptyTable: "No support requests found.",
            zeroRecords: "No matching requests found."
        }
    });
});

function showToast(message, type = 'success') {
    const toast = document.getElementById('toastNotification');
    toast.className = 'toast-' + type;
    toast.textContent = message;
    toast.style.display = 'flex';

    setTimeout(() => {
        toast.style.display = 'none';
    }, 4000);
}

function filterTable(statusFilter, buttonElement) {
    document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
    if (buttonElement) buttonElement.classList.add('active');

    if (!dataTable) return;

    if (statusFilter === 'all') {
        dataTable.column(7).search('').draw();
    } else {
        // Regex search matching the status word inside the badge in column 7
        const capitalized = statusFilter.charAt(0).toUpperCase() + statusFilter.slice(1);
        dataTable.column(7).search(capitalized, true, false).draw();
    }
}

function handleModalOverlayClick(e) {
    if (e.target.id === 'ticketModal') {
        closeTicketModal();
    }
}

function closeTicketModal() {
    document.getElementById('ticketModal').style.display = 'none';
    cancelDenyForm();
    currentActiveTicket = null;
}

function openTicketModal(ticketId, triggerAction = null) {
    const modal = document.getElementById('ticketModal');
    const loading = document.getElementById('modalLoading');
    const dataContainer = document.getElementById('modalDataContainer');
    const actionButtons = document.getElementById('modalActionButtons');
    
    cancelDenyForm();
    modal.style.display = 'block';
    loading.style.display = 'block';
    dataContainer.style.display = 'none';
    actionButtons.style.display = 'none';

    fetch(`support.php?action=get_details&ticket_id=${encodeURIComponent(ticketId)}`)
        .then(res => res.json())
        .then(data => {
            if (!data.success) {
                loading.textContent = data.message || 'Failed to load ticket details.';
                return;
            }

            currentActiveTicket = data.ticket;
            populateModal(data);
            loading.style.display = 'none';
            dataContainer.style.display = 'flex';

            if (triggerAction === 'deny') {
                toggleDenyForm();
            }
        })
        .catch(err => {
            console.error(err);
            loading.textContent = 'Error connecting to server. Please try again.';
        });
}

function populateModal(data) {
    const ticket = data.ticket;
    const files = data.files || [];
    const order = data.order;

    document.getElementById('modalTicketRef').textContent = ticket.ticket_ref;
    
    // Status Badge
    const currentStatus = (ticket.status || 'pending').toLowerCase();
    const badge = document.getElementById('modalStatusBadge');
    badge.className = 'badge badge-' + currentStatus;
    badge.textContent = currentStatus.charAt(0).toUpperCase() + currentStatus.slice(1);

    // Customer & Ticket fields
    document.getElementById('modalCustomerName').textContent = `${ticket.first_name || ''} ${ticket.last_name || ''}`.trim() || '—';
    document.getElementById('modalCustomerEmail').textContent = ticket.email || '—';
    document.getElementById('modalOrderId').textContent = ticket.order_id || '—';
    document.getElementById('modalDeliveryDate').textContent = ticket.delivery_date ? formatDate(ticket.delivery_date) : '—';
    document.getElementById('modalFishSku').textContent = ticket.fish_sku || '—';
    document.getElementById('modalDoaCount').textContent = ticket.doa_count || '0';

    let resolutionText = (ticket.resolution || '').replace('-', ' ');
    resolutionText = resolutionText.charAt(0).toUpperCase() + resolutionText.slice(1);
    if (ticket.resolution === 'other' && ticket.resolution_other) {
        resolutionText += `: ${ticket.resolution_other}`;
    }
    document.getElementById('modalResolution').textContent = resolutionText || '—';
    document.getElementById('modalCreatedAt').textContent = ticket.created_at ? formatDate(ticket.created_at) : '—';
    document.getElementById('modalDescription').textContent = ticket.description || 'No description provided.';

    // Admin response
    const adminRespContainer = document.getElementById('modalAdminResponseContainer');
    const adminRespText = document.getElementById('modalAdminResponseText');
    if (currentStatus === 'denied' && ticket.admin_response) {
        adminRespText.textContent = ticket.admin_response;
        adminRespContainer.style.display = 'block';
    } else {
        adminRespContainer.style.display = 'none';
        adminRespText.textContent = '';
    }

    // Related Order
    const orderFoundDiv = document.getElementById('modalOrderFound');
    const orderNotFoundDiv = document.getElementById('modalOrderNotFound');
    if (order) {
        orderNotFoundDiv.style.display = 'none';
        orderFoundDiv.style.display = 'grid';

        document.getElementById('modalRelOrderId').textContent = order.order_id || '—';
        document.getElementById('modalRelBuyerName').textContent = `${order.first_name || ''} ${order.last_name || ''}`.trim() || '—';
        document.getElementById('modalRelEmail').textContent = order.email || '—';
        document.getElementById('modalRelCountry').textContent = order.country || '—';
        
        const totalVal = parseFloat(order.total_value || 0).toFixed(2);
        document.getElementById('modalRelAmount').textContent = `$${totalVal}`;
        
        const orderDateStr = order.order_date || order.created_at;
        document.getElementById('modalRelDate').textContent = orderDateStr ? formatDate(orderDateStr) : '—';
        document.getElementById('modalRelSku').textContent = order.sku || '—';
    } else {
        orderFoundDiv.style.display = 'none';
        orderNotFoundDiv.style.display = 'block';
    }

    // Evidence
    const evidenceCount = document.getElementById('modalEvidenceCount');
    const evidenceContainer = document.getElementById('modalEvidenceContainer');
    evidenceCount.textContent = `${files.length} file${files.length === 1 ? '' : 's'}`;

    if (files.length === 0) {
        evidenceContainer.innerHTML = '<div class="empty-evidence">No evidence uploaded</div>';
    } else {
        let gridHtml = '<div class="evidence-grid">';
        files.forEach(f => {
            const safeName = escapeHtml(f.original_filename);
            const safeSize = escapeHtml(f.size_formatted);
            
            if (f.exists && f.url) {
                const safeUrl = f.url;
                gridHtml += `
                    <div class="evidence-card" onclick="openLightbox('${safeUrl}', '${safeName}')">
                        <img class="evidence-thumb" src="${safeUrl}" alt="${safeName}" loading="lazy">
                        <div class="evidence-meta">
                            <span class="evidence-filename" title="${safeName}">${safeName}</span>
                            <span>${safeSize}</span>
                        </div>
                    </div>
                `;
            } else {
                gridHtml += `
                    <div class="evidence-card" style="cursor:default;">
                        <div class="evidence-placeholder">File not found on disk</div>
                        <div class="evidence-meta">
                            <span class="evidence-filename" title="${safeName}">${safeName}</span>
                            <span>${safeSize}</span>
                        </div>
                    </div>
                `;
            }
        });
        gridHtml += '</div>';
        evidenceContainer.innerHTML = gridHtml;
    }

    // Modal action buttons
    const actionButtons = document.getElementById('modalActionButtons');
    if (currentStatus === 'pending') {
        actionButtons.style.display = 'inline-flex';
    } else {
        actionButtons.style.display = 'none';
    }
}

function formatDate(dateStr) {
    try {
        const d = new Date(dateStr.replace(' ', 'T'));
        if (isNaN(d.getTime())) return dateStr;
        return d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
    } catch (e) {
        return dateStr;
    }
}

function escapeHtml(str) {
    if (!str) return '';
    return str.replace(/[&<>"']/g, m => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#39;'
    })[m]);
}

function openLightbox(url, caption) {
    const lightbox = document.getElementById('imageLightbox');
    document.getElementById('lightboxImage').src = url;
    document.getElementById('lightboxCaption').textContent = caption;
    lightbox.style.display = 'flex';
}

function closeLightbox(e) {
    if (e.target.id === 'imageLightbox' || e.target.id === 'lightboxClose') {
        document.getElementById('imageLightbox').style.display = 'none';
        document.getElementById('lightboxImage').src = '';
    }
}

function confirmApprove(ticketId, ticketRef) {
    if (confirm(`Are you sure you want to approve this refund request (${ticketRef})?`)) {
        executeApproval(ticketId);
    }
}

function triggerModalApprove() {
    if (!currentActiveTicket) return;
    confirmApprove(currentActiveTicket.ticket_id, currentActiveTicket.ticket_ref);
}

function executeApproval(ticketId) {
    fetch('support.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'approve', ticket_id: ticketId })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            showToast(data.message, 'success');
            updateUIAfterStatusChange(ticketId, 'approved');
            if (currentActiveTicket && currentActiveTicket.ticket_id === ticketId) {
                openTicketModal(ticketId);
            }
        } else {
            showToast(data.message || 'Approval failed.', 'error');
        }
    })
    .catch(err => {
        console.error(err);
        showToast('Network error while approving request.', 'error');
    });
}

function openDenyModal(ticketId, ticketRef) {
    openTicketModal(ticketId, 'deny');
}

function toggleDenyForm() {
    const container = document.getElementById('denyFormContainer');
    container.style.display = 'block';
    document.getElementById('denyReasonInput').focus();
    const modalBody = document.getElementById('modalBodyContent');
    modalBody.scrollTop = modalBody.scrollHeight;
}

function cancelDenyForm() {
    const container = document.getElementById('denyFormContainer');
    if (container) {
        container.style.display = 'none';
        document.getElementById('denyReasonInput').value = '';
    }
}

function submitDenial() {
    if (!currentActiveTicket) return;
    const ticketId = currentActiveTicket.ticket_id;
    const reason = document.getElementById('denyReasonInput').value.trim();

    if (!reason) {
        alert('Please enter an admin response / reason for denying this request.');
        document.getElementById('denyReasonInput').focus();
        return;
    }

    fetch('support.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            action: 'deny',
            ticket_id: ticketId,
            admin_response: reason
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            showToast(data.message, 'success');
            updateUIAfterStatusChange(ticketId, 'denied');
            cancelDenyForm();
            openTicketModal(ticketId);
        } else {
            showToast(data.message || 'Denial failed.', 'error');
        }
    })
    .catch(err => {
        console.error(err);
        showToast('Network error while denying request.', 'error');
    });
}

function updateUIAfterStatusChange(ticketId, newStatus) {
    const row = document.getElementById(`row-ticket-${ticketId}`);
    if (row) {
        const oldStatus = row.getAttribute('data-status');
        row.setAttribute('data-status', newStatus);

        const badge = document.getElementById(`badge-ticket-${ticketId}`);
        if (badge) {
            badge.className = `badge badge-${newStatus}`;
            badge.textContent = newStatus.charAt(0).toUpperCase() + newStatus.slice(1);
        }

        const actionsContainer = document.getElementById(`actions-ticket-${ticketId}`);
        if (actionsContainer) {
            actionsContainer.innerHTML = `
                <button class="btn btn-view" onclick="openTicketModal(${ticketId})">View</button>
            `;
        }

        // Invalidate row in DataTables so search and sorting stay in sync
        if (dataTable) {
            dataTable.row(row).invalidate().draw(false);
        }

        if (oldStatus === 'pending') {
            const pCount = document.getElementById('badgePendingCount');
            if (pCount) pCount.textContent = Math.max(0, parseInt(pCount.textContent || 0) - 1);
        }
        if (newStatus === 'approved') {
            const aCount = document.getElementById('badgeApprovedCount');
            if (aCount) aCount.textContent = parseInt(aCount.textContent || 0) + 1;
        } else if (newStatus === 'denied') {
            const dCount = document.getElementById('badgeDeniedCount');
            if (dCount) dCount.textContent = parseInt(dCount.textContent || 0) + 1;
        }
    }
}
</script>

</body>
</html>