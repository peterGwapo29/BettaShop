<?php
/**
 * config.inc.php — LOCAL DEVELOPMENT VERSION
 *
 * The client confirmed this file only contains session settings (no DB
 * credentials, no production secrets). This local copy was created because
 * it was not included in the files provided. It intentionally contains
 * nothing but session setup so it is safe to keep out of version control's
 * "sensitive" bucket, and so it does not conflict with whatever the real
 * production config.inc.php configures.
 *
 * Do NOT commit this over the production config.inc.php.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',      // localhost — leave blank
        'secure'   => false,   // local dev is plain HTTP; set true in production (HTTPS)
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

/**
 * LOCAL-ONLY ADDITION (not part of the "session settings only" description).
 *
 * otp_email.api.php reads $_ENV['domain'] as an OTP-email placeholder. With
 * no .env/environment setup locally, that's an undefined array key — and
 * because otp_email.api.php sends `Content-Type: application/json` and then
 * echoes JSON, a visible PHP warning would get printed *before* the JSON and
 * break `response.json()` on the JS side (XAMPP ships with display_errors
 * On by default). This default prevents that without touching the API file.
 * Safe to remove once the real config/.env supplies this value.
 */
if (!isset($_ENV['domain'])) {
    $_ENV['domain'] = 'localhost';
}
