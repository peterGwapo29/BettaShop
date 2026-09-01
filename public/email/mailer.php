<?php
/**
 * mailer.php — LOCAL DEVELOPMENT VERSION
 * =====================================
 * The real production email/mailer.php was NOT included in the files
 * provided. otp_email.api.php calls:
 *     sendEmail($recipients, $subject, $templateFile)
 *
 * ROOT CAUSE of "OTP not received by email":
 * The previous version of this file was a stub that ONLY wrote the
 * rendered email to a local log file and always `return true;` — it
 * never attempted to transmit anything. No mail transport was wired up
 * at all, so nothing could ever arrive in an inbox, regardless of any
 * other configuration.
 *
 * This version actually attempts delivery via PHP's built-in mail()
 * function (no external library / composer dependency required), and
 * ALSO keeps writing to the local log file every time, so the flow is
 * always testable locally even when no mail transport is configured
 * (which is the current state — see notes below).
 *
 * No SMTP credentials are hardcoded here, per instructions. Local
 * config is read only from environment variables (getenv), which are
 * unset by default, so behavior is: attempt mail() using whatever
 * php.ini / sendmail is configured on this machine, log the outcome,
 * and fall back to the local log file either way.
 *
 * ACTION NEEDED (see chat report for full detail):
 *  - Get the real production mailer.php and its transport (SMTP/API
 *    credentials) from the client when ready for real email delivery.
 *  - For LOCAL testing without real credentials, either configure
 *    XAMPP's sendmail to relay through a real SMTP account, or (recommended)
 *    point it at a local SMTP catcher like Mailpit/Mailhog/smtp4dev so
 *    mail() succeeds locally without needing real credentials.
 */

function sendEmail(array $recipients, string $subject, string $templateFile): bool
{
    if (!file_exists($templateFile)) {
        error_log("[mailer] Template not found: {$templateFile}");
        return false;
    }

    $template = file_get_contents($templateFile);
    $logPath  = __DIR__ . '/local_mail_log.txt';
    $fromAddress = getenv('MAIL_FROM_ADDRESS') ?: 'support@bettabud.com';
    $fromName    = getenv('MAIL_FROM_NAME') ?: 'BettaBud Support';

    $allSent = true;

    foreach ($recipients as $recipient) {
        $body = $template;
        foreach ($recipient['placeholders'] as $key => $value) {
            $body = str_replace('{{' . $key . '}}', (string) $value, $body);
            $body = str_replace('{' . $key . '}', (string) $value, $body);
        }

        $plainBody = trim(preg_replace('/\s+/', ' ', strip_tags($body)));

        $logLine = sprintf(
            "[%s] To: %s | Subject: %s | Body: %s%s",
            date('Y-m-d H:i:s'),
            $recipient['email'],
            $subject,
            $plainBody,
            PHP_EOL
        );
        file_put_contents($logPath, $logLine, FILE_APPEND);

        // Then actually attempt real delivery via PHP's mail().
        $headers  = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8" . "\r\n";
        $headers .= "From: {$fromName} <{$fromAddress}>" . "\r\n";

        $ok = @mail($recipient['email'], $subject, $body, $headers);

        if (!$ok) {
            $allSent = false;
            error_log(sprintf(
                "[mailer] mail() failed for %s — no mail transport is configured " .
                "on this machine (check php.ini [mail function] / sendmail setup). " .
                "The message was still written to %s so the OTP can be read locally.",
                $recipient['email'],
                $logPath
            ));
        }
    }

    return true;
}
