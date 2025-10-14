<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "PHP OK\n";

$paths = [
  __DIR__ . '/mail_send.php',
  __DIR__ . '/mail_config.php',
  __DIR__ . '/PHPMailer-master/src/PHPMailer.php', // change if you use lib/PHPMailer
];

foreach ($paths as $p) {
  echo (file_exists($p) ? "[OK] " : "[MISS] ") . $p . "\n";
}

require __DIR__ . '/mail_send.php';

$ok = send_login_email([
  'username'   => 'TEST_USER',
  'ip'         => $_SERVER['REMOTE_ADDR'] ?? '',
  'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
  'created_at' => date('c'),
]);

echo $ok ? "MAIL SENT\n" : "MAIL FAILED — check error log\n";
