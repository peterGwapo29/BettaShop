<?php

require_once __DIR__ . "/../includes/config.inc.php";
require_once __DIR__ . "/../email/mailer.php";

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim($_POST['action'] ?? '');

    if ($action === 'start_refresh_cooldown') {
        $_SESSION['otp_cooldown_until'] = time() + 60;
        unset($_SESSION['otp']);
        unset($_SESSION['verified_email']);
        unset($_SESSION['resend_cooldown_until']);
        echo json_encode([
            'success' => true,
            'cooldown_remaining' => 60,
        ]);
        exit;
    }

    $email = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL);

    if (!$email) {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid email address.'
        ]);
        exit;
    }

    $now = time();
    $emailKey = strtolower($email);
    $cooldownRemaining = 0;

    // Check if refresh cooldown is currently active
    if (!empty($_SESSION['otp_cooldown_until'])) {
        $refreshRemaining = (int)$_SESSION['otp_cooldown_until'] - $now;
        if ($refreshRemaining > 0) {
            $cooldownRemaining = max($cooldownRemaining, $refreshRemaining);
        } else {
            unset($_SESSION['otp_cooldown_until']);
        }
    }

    // Check resend cooldown if this is a resend request
    $isResend = !empty($_POST['is_resend']);
    if ($isResend && !empty($_SESSION['resend_cooldown_until'][$emailKey])) {
        $resendRemaining = (int)$_SESSION['resend_cooldown_until'][$emailKey] - $now;
        if ($resendRemaining > 0) {
            $cooldownRemaining = max($cooldownRemaining, $resendRemaining);
        } else {
            unset($_SESSION['resend_cooldown_until'][$emailKey]);
        }
    }

    if ($cooldownRemaining > 0) {
        echo json_encode([
            'success' => false,
            'message' => 'A new verification code can only be sent after 60 seconds. Please wait ' . $cooldownRemaining . ' seconds and try again.',
            'cooldown_remaining' => $cooldownRemaining,
        ]);
        exit;
    }
    
    $_SESSION['otp']['key'] = random_int(100000, 999999);
    $_SESSION['otp']['email'] = $email;
    $_SESSION['otp']['expiry'] = time() + 900;
    $_SESSION['otp_last_requested_at'] = time();
    $_SESSION['otp_last_requested_email'] = $email;

    if ($isResend) {
        $_SESSION['resend_cooldown_until'] = $_SESSION['resend_cooldown_until'] ?? [];
        $_SESSION['resend_cooldown_until'][$emailKey] = time() + 60;
    }
    
    $subject = "Your Verification Code";
    $templateFile = __DIR__ . '/../email/templates/otp.html';
    
    $recipients = [
        [
            'email' => $email,
            'placeholders' => [
                'otp' => $_SESSION['otp']['key'],
                'domain' => $_ENV['domain'] ?? 'localhost'
            ],
        ]
    ];
    
    $sent = sendEmail($recipients, $subject, $templateFile);

    if (!$sent) {
        error_log("[otp_email.api.php] sendEmail() reported failure for {$email}");
        unset($_SESSION['otp']);
        if ($isResend) {
            unset($_SESSION['resend_cooldown_until'][$emailKey]);
        }
        unset($_SESSION['otp_last_requested_at']);
        unset($_SESSION['otp_last_requested_email']);

        echo json_encode([
            'success' => false,
            'message' => 'Unable to send verification code. Please try again shortly.'
        ]);
        exit;
    }

    echo json_encode([
        'success' => true,
        'cooldown_remaining' => $isResend ? 60 : 0,
    ]);
    
    exit;
}


