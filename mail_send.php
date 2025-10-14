<?php
// mail_send.php — HTML formatted version with bold + underline labels

require __DIR__ . '/lib/PHPMailer/PHPMailer.php';
require __DIR__ . '/lib/PHPMailer/SMTP.php';
require __DIR__ . '/lib/PHPMailer/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function send_login_email(array $payload): bool {
    $cfg = require __DIR__ . '/mail_config.php';

    date_default_timezone_set('Asia/Beirut');
    $host     = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $username = htmlspecialchars($payload['username'] ?? 'unknown');
    $userId   = htmlspecialchars($payload['user_id'] ?? 'n/a');
    $ip       = htmlspecialchars($payload['ip'] ?? 'n/a');
    $when     = htmlspecialchars($payload['when'] ?? date('Y-m-d H:i:s') . ' Asia/Beirut');
    $ua       = htmlspecialchars($payload['user_agent'] ?? 'n/a');
    $success  = (bool)($payload['success'] ?? true);
    $reason   = htmlspecialchars($payload['reason'] ?? '');
    $city     = htmlspecialchars($payload['city'] ?? '');
    $country  = htmlspecialchars($payload['country'] ?? '');
    $ref      = htmlspecialchars($payload['referrer'] ?? ($_SERVER['HTTP_REFERER'] ?? ''));

    $loc = trim($city . (($city && $country) ? ', ' : '') . $country);
    $result = $success ? 'SUCCESS' : 'FAILED';

    // ---------------- HTML Body ----------------
    $body = "
    <div style='font-family:Segoe UI,Roboto,Arial,sans-serif;font-size:15px;line-height:1.6;color:#202124'>
      <h2 style='margin-bottom:6px'>Login Notification</h2>
      <hr style='border:none;border-top:1px solid #ddd;margin:8px 0'>
      <p><b><u>Result</u>:</b> $result</p>
      <p><b><u>Username</u>:</b> $username</p>
      <p><b><u>User ID</u>:</b> $userId</p>
      <p><b><u>Time</u>:</b> $when</p>
      <p><b><u>IP</u>:</b> $ip</p>
      <p><b><u>Location</u>:</b> " . ($loc ?: 'n/a') . "</p>
      <p><b><u>User-Agent</u>:</b> $ua</p>
      <p><b><u>Referrer</u>:</b> " . ($ref ? "<a href='$ref'>$ref</a>" : 'n/a') . "</p>
      <p><b><u>Host</u>:</b> $host</p>";

    if (!$success && $reason !== '') {
        $body .= "<p><b><u>Reason</u>:</b> $reason</p>";
    }

    $body .= "
      <hr style='border:none;border-top:1px solid #ddd;margin:12px 0'>
      <p style='color:#d93025'><i>If this wasn’t you, please reset your password immediately.</i></p>
    </div>";

    // ---------------- PHPMailer ----------------
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = $cfg['smtp_host'];
        $mail->Port       = (int)$cfg['smtp_port'];
        $mail->SMTPAuth   = true;
        $mail->SMTPSecure = $cfg['smtp_secure'];
        $mail->Username   = $cfg['smtp_user'];
        $mail->Password   = $cfg['smtp_pass'];
        $mail->CharSet    = 'UTF-8';
        $mail->Timeout    = 20;
        $mail->SMTPKeepAlive = false;
        $mail->SMTPOptions = [
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true,
            ]
        ];

        $mail->setFrom($cfg['from_email'], $cfg['from_name']);
        $mail->addAddress($cfg['to_email'], $cfg['to_name']);
        $mail->addReplyTo($cfg['from_email'], $cfg['from_name']);

        $mail->Subject = "Login $result — $host — $username — $when";
        $mail->isHTML(true);
        $mail->Body    = $body;
        $mail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '<p>', '</p>'], ["\n", "\n", "", "\n"], $body));

        return $mail->send();
    } catch (Exception $e) {
        error_log('[MAILER] send_login_email failed: ' . $mail->ErrorInfo . ' | ' . $e->getMessage());
        return false;
    }
}
