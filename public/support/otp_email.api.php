<?php

require_once __DIR__ . "/../includes/config.inc.php";
require_once __DIR__ . "/../email/mailer.php";

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_var($_POST['email'], FILTER_VALIDATE_EMAIL);

    if (!$email) {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid email address.'
        ]);
        exit;
    }

    $cooldownSeconds = 60;
    $emailKey = strtolower($email);
    $_SESSION['otp_requests'] = $_SESSION['otp_requests'] ?? [];
    $lastRequestAt = $_SESSION['otp_requests'][$emailKey] ?? null;
    if ($lastRequestAt !== null) {
        $secondsRemaining = $cooldownSeconds - (time() - (int) $lastRequestAt);
        if ($secondsRemaining > 0) {
            echo json_encode([
                'success' => false,
                'message' => 'A new verification code can only be sent after 60 seconds. Please wait ' . $secondsRemaining . ' seconds and try again.',
                'cooldown_remaining' => $secondsRemaining,
            ]);
            exit;
        }
    }
    
    $_SESSION['otp']['key'] = random_int(100000, 999999);
    $_SESSION['otp']['email'] = $email;
    $_SESSION['otp']['expiry'] = time() + 900;
    $_SESSION['otp_requests'][$emailKey] = time();
    $_SESSION['otp_last_requested_at'] = time();
    $_SESSION['otp_last_requested_email'] = $email;
    
    $subject = "Your Verification Code";
    $templateFile = __DIR__ . '/../email/templates/otp.html';
    
    $recipients = [
        [
            'email' => $email,
            'placeholders' => [
                'otp' => $_SESSION['otp']['key'],
                'domain' => $_ENV['domain']
            ],
        ]
    ];
    
    $sent = sendEmail($recipients, $subject, $templateFile);

    if (!$sent) {
        // Previously this branch didn't exist — sendEmail()'s return
        // value was discarded and the API always echoed success:true,
        // even when the email genuinely failed to go out. That masked
        // the real problem: the frontend would happily advance to the
        // OTP-entry screen with no code ever delivered.
        error_log("[otp_email.api.php] sendEmail() reported failure for {$email}");
        unset($_SESSION['otp']);
        unset($_SESSION['otp_requests'][strtolower($email)]);
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
        'cooldown_remaining' => 60,
    ]);
    
    exit;
}

