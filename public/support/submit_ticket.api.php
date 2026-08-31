<?php
/**
 * submit_ticket.api.php
 * ========================
 * Real ticket submission for the support/refund form (Step 3 of the
 * existing flow). Replaces the fake client-side-only submission in
 * index.php's "Submission" script block.
 *
 * Follows the same conventions as the existing otp_email.api.php /
 * otp_verify.api.php: JSON content-type, POST-only, JSON success/message
 * response shape, __DIR__-based requires.
 *
 * Access requires a verified email in the session (set by
 * otp_verify.api.php) — a customer cannot reach this endpoint without
 * having completed the OTP step first, regardless of what the client
 * sends.
 */

require_once __DIR__ . "/../includes/config.inc.php";
require_once __DIR__ . "/../includes/dbh.inc.php";
require_once __DIR__ . "/../includes/users.class.php";
require_once __DIR__ . "/../includes/support.class.php";

header('Content-Type: application/json');

function fail(string $message, int $httpStatus = 400): void
{
    http_response_code($httpStatus);
    echo json_encode(['success' => false, 'message' => $message]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    fail('Invalid request method.', 405);
}

// ── Must have completed OTP verification this session ──
if (empty($_SESSION['verified_email'])) {
    fail('Your session has expired. Please verify your email again.', 401);
}
$verifiedEmail = $_SESSION['verified_email'];

// ── Collect + validate required text fields ──
$firstName       = trim($_POST['firstName'] ?? '');
$lastName        = trim($_POST['lastName'] ?? '');
$orderId         = trim($_POST['orderNumber'] ?? '');
$deliveryDate    = trim($_POST['deliveryDate'] ?? '');
$doaCount        = $_POST['doaCount'] ?? '';
$resolution      = trim($_POST['resolution'] ?? '');
$resolutionOther = trim($_POST['resolutionOther'] ?? '');
$description     = trim($_POST['description'] ?? '');
$fishSku         = $_POST['fishSku'] ?? [];
$policyAck       = $_POST['policyAck'] ?? '';

if ($firstName === '' || $lastName === '' || $orderId === '' || $deliveryDate === ''
    || $doaCount === '' || $resolution === '' || $description === '') {
    fail('Please fill in all required fields.');
}

if (!ctype_digit((string) $doaCount) || (int) $doaCount < 1) {
    fail('Please enter a valid quantity of DOA fish.');
}
$doaCount = (int) $doaCount;

$dateObj = DateTime::createFromFormat('Y-m-d', $deliveryDate);
if (!$dateObj || $dateObj->format('Y-m-d') !== $deliveryDate) {
    fail('Please provide a valid delivery date.');
}

$allowedResolutions = ['store-credit', 'replacement', 'other'];
if (!in_array($resolution, $allowedResolutions, true)) {
    fail('Please select a valid resolution.');
}
if ($resolution === 'other' && $resolutionOther === '') {
    fail('Please describe your preferred resolution.');
}
if ($resolution !== 'other') {
    $resolutionOther = null;
}

if (!is_array($fishSku) || count($fishSku) === 0) {
    fail('Please select at least one SKU or "I don\'t know".');
}
// Sanitize each selected value — these are checkbox values from our own
// markup, but never trust POST data blindly.
$fishSku = array_map('trim', $fishSku);
$fishSku = array_filter($fishSku, fn($v) => $v !== '');
$fishSkuStored = implode(', ', $fishSku);

if ($policyAck !== 'on' && $policyAck !== '1' && $policyAck !== 'true') {
    fail('You must acknowledge the DOA policy.');
}

// ── Re-verify the order actually belongs to the verified email ──
// (the <select> is populated from the server, but POST data from the
// browser is never trusted as-is)
$customerOrders = users::getUserOrdersByEmail($pdo, $verifiedEmail);
$orderIds       = array_column($customerOrders, 'order_id');
if (!in_array($orderId, $orderIds, true)) {
    fail('That order could not be matched to your verified email.');
}

// ── Validate uploaded files (optional, but validated if present) ──
$maxFiles      = 10;
$maxSizeBytes  = 10 * 1024 * 1024; // 10 MB, matches the UI copy
$allowedMimes  = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/heic' => 'heic',
    'image/heif' => 'heic',
];

$filesToStore = []; // [tmp_path, original_name, mime, size, ext]

if (!empty($_FILES['photos']) && is_array($_FILES['photos']['name'])) {
    $fileCount = count($_FILES['photos']['name']);

    if ($fileCount > $maxFiles) {
        fail("Please upload no more than {$maxFiles} photos.");
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);

    for ($i = 0; $i < $fileCount; $i++) {
        if ($_FILES['photos']['error'][$i] === UPLOAD_ERR_NO_FILE) {
            continue; // empty slot, skip
        }
        if ($_FILES['photos']['error'][$i] !== UPLOAD_ERR_OK) {
            finfo_close($finfo);
            fail('One of the uploaded files failed to upload. Please try again.');
        }

        $tmpPath = $_FILES['photos']['tmp_name'][$i];
        $size    = (int) $_FILES['photos']['size'][$i];

        if ($size > $maxSizeBytes) {
            finfo_close($finfo);
            fail('Each photo must be 10 MB or smaller.');
        }

        // Detect the real MIME type from the file's actual bytes —
        // never trust $_FILES[...]['type'], which is browser-supplied.
        $realMime = finfo_file($finfo, $tmpPath);

        if (!isset($allowedMimes[$realMime])) {
            finfo_close($finfo);
            fail('Only JPG, PNG, or HEIC photos are allowed.');
        }

        // Also confirm it's a genuine, decodable image (defense in depth
        // against files that fake their MIME signature).
        if (in_array($realMime, ['image/jpeg', 'image/png'], true) && @getimagesize($tmpPath) === false) {
            finfo_close($finfo);
            fail('One of the uploaded files is not a valid image.');
        }

        $filesToStore[] = [
            'tmp_path'      => $tmpPath,
            'original_name' => basename($_FILES['photos']['name'][$i]), // never trust for building paths
            'mime'          => $realMime,
            'size'          => $size,
            'ext'           => $allowedMimes[$realMime],
        ];
    }

    finfo_close($finfo);
}

// ── Create the ticket ──
try {
    $pdo->beginTransaction();

    $ticket = support::createTicket($pdo, [
        'email'            => $verifiedEmail,
        'first_name'       => $firstName,
        'last_name'        => $lastName,
        'order_id'         => $orderId,
        'delivery_date'    => $deliveryDate,
        'fish_sku'         => $fishSkuStored,
        'doa_count'        => $doaCount,
        'resolution'       => $resolution,
        'resolution_other' => $resolutionOther,
        'description'      => $description,
    ]);

    $ticketId = $ticket['ticket_id'];

    // ── Move validated files to their final location ──
    $uploadDir = __DIR__ . "/../uploads/tickets/{$ticketId}";
    if (!empty($filesToStore) && !is_dir($uploadDir)) {
        if (!mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
            throw new RuntimeException('Could not create upload directory.');
        }
    }

    foreach ($filesToStore as $file) {
        // Fully server-generated filename — the original name is never
        // used to build a path, only stored for display purposes.
        $storedName = bin2hex(random_bytes(16)) . '.' . $file['ext'];
        $destPath   = $uploadDir . '/' . $storedName;

        if (!move_uploaded_file($file['tmp_path'], $destPath)) {
            throw new RuntimeException('Could not save an uploaded file.');
        }

        support::saveTicketFile(
            $pdo,
            $ticketId,
            $file['original_name'],
            $storedName,
            $file['mime'],
            $file['size']
        );
    }

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    // Never expose internal error details to the customer.
    error_log('[submit_ticket.api.php] ' . $e->getMessage());
    fail('Something went wrong while submitting your request. Please try again.', 500);
}

echo json_encode([
    'success'    => true,
    'ticket_ref' => $ticket['ticket_ref'],
]);
exit;
