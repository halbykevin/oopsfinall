<?php
// mail_send.php — HTML formatted versions with bold + underline labels

require __DIR__ . '/lib/PHPMailer/PHPMailer.php';
require __DIR__ . '/lib/PHPMailer/SMTP.php';
require __DIR__ . '/lib/PHPMailer/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * LOGIN NOTIFICATION
 */
function send_login_email(array $payload): bool {
    $cfg = require __DIR__ . '/mail_config.php';

    date_default_timezone_set('Asia/Beirut');
    $host     = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $username = htmlspecialchars($payload['username'] ?? 'unknown', ENT_QUOTES, 'UTF-8');
    $userId   = htmlspecialchars($payload['user_id'] ?? 'n/a', ENT_QUOTES, 'UTF-8');
    $ip       = htmlspecialchars($payload['ip'] ?? 'n/a', ENT_QUOTES, 'UTF-8');
    $when     = htmlspecialchars($payload['when'] ?? date('Y-m-d H:i:s') . ' Asia/Beirut', ENT_QUOTES, 'UTF-8');
    $ua       = htmlspecialchars($payload['user_agent'] ?? 'n/a', ENT_QUOTES, 'UTF-8');
    $success  = (bool)($payload['success'] ?? true);
    $reason   = htmlspecialchars($payload['reason'] ?? '', ENT_QUOTES, 'UTF-8');
    $city     = htmlspecialchars($payload['city'] ?? '', ENT_QUOTES, 'UTF-8');
    $country  = htmlspecialchars($payload['country'] ?? '', ENT_QUOTES, 'UTF-8');
    $refRaw   = $payload['referrer'] ?? ($_SERVER['HTTP_REFERER'] ?? '');
    $ref      = htmlspecialchars($refRaw, ENT_QUOTES, 'UTF-8');

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
      <p><b><u>Referrer</u>:</b> " . ($refRaw ? "<a href='$ref'>$ref</a>" : 'n/a') . "</p>
      <p><b><u>Host</u>:</b> " . htmlspecialchars($host, ENT_QUOTES, 'UTF-8') . "</p>";

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

        $mail->Subject = "Login $result — " . ($host ?? 'host') . " — $username — $when";
        $mail->isHTML(true);
        $mail->Body    = $body;
        $mail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '<p>', '</p>'], ["\n", "\n", "", "\n"], $body));

        return $mail->send();
    } catch (Exception $e) {
        error_log('[MAILER] send_login_email failed: ' . $mail->ErrorInfo . ' | ' . $e->getMessage());
        return false;
    }
}

/**
 * FEEDBACK NOTIFICATION
 * Expects keys: fname, lname, dateofbirth, number, email, experience, food, service, atmosphere,
 *               text1, text2, submitted_at, ip, user_agent
 */
function send_feedback_email(array $fb): bool {
    $cfg = require __DIR__ . '/mail_config.php';

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

        // Helpers
        $esc = static function($v) {
            return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        };
        $stars = static function($n) {
            $n = max(0, min(5, (int)$n));
            return str_repeat('★', $n) . str_repeat('☆', 5 - $n);
        };

        $submitted = $esc($fb['submitted_at'] ?? '');
        $name = trim(($fb['fname'] ?? '') . ' ' . ($fb['lname'] ?? ''));
        $mail->Subject = 'New Feedback — ' . ($name !== '' ? $name : 'Unknown') . ($submitted ? " — $submitted" : '');

        // Build rows
        $rows = [
            'First Name'         => $esc($fb['fname'] ?? ''),
            'Last Name'          => $esc($fb['lname'] ?? ''),
            'Date of Birth'      => $esc($fb['dateofbirth'] ?? ''),
            'Phone Number'       => $esc($fb['number'] ?? ''),
            'Email'              => $esc($fb['email'] ?? ''),
            'Overall Experience' => $stars($fb['experience'] ?? 0),
            'Food'               => $stars($fb['food'] ?? 0),
            'Service'            => $stars($fb['service'] ?? 0),
            'Atmosphere'         => $stars($fb['atmosphere'] ?? 0),
            'Keep Doing'         => nl2br($esc($fb['text1'] ?? '')),
            'Should Change'      => nl2br($esc($fb['text2'] ?? '')),
            'Submitted At'       => $submitted,
            'IP'                 => $esc($fb['ip'] ?? ''),
            'User-Agent'         => $esc($fb['user_agent'] ?? ''),
        ];

        // HTML body
        ob_start(); ?>
        <div style="font:14px/1.55 -apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Arial,sans-serif;color:#202124">
          <h2 style="margin:0 0 6px 0; font:bold 18px Montserrat,Arial,sans-serif; color:#9E3722;">
            New Feedback Received
          </h2>
          <hr style="border:none;border-top:1px solid #e6e6e6;margin:8px 0 12px">
          <?php foreach ($rows as $label => $val): ?>
            <div style="margin:8px 0;">
              <div style="font-weight:700; text-decoration:underline; color:#9E3722;"><?= $esc($label) ?>:</div>
              <div><?= ($val !== '' ? $val : '<span style="color:#9aa0a6;">(empty)</span>') ?></div>
            </div>
          <?php endforeach; ?>
        </div>
        <?php
        $mail->isHTML(true);
        $mail->Body = ob_get_clean();

        // Plain-text fallback
        $plain = [];
        foreach ($rows as $k => $v) {
            $plain[] = $k . ': ' . trim(strip_tags((string)$v));
        }
        $mail->AltBody = implode("\n", $plain);

        return $mail->send();
    } catch (Exception $e) {
        error_log('[MAILER] send_feedback_email failed: ' . $mail->ErrorInfo . ' | ' . $e->getMessage());
        return false;
    }
}
