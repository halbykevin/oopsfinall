<?php
// mail_config.php
return [
  'smtp_host'   => 'smtp.gmail.com',
  'smtp_port'   => 587,
  'smtp_secure' => PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS,

  // Gmail you used for the App Password
  'smtp_user'   => 'kevinnhalby@gmail.com',
  'smtp_pass'   => 'qcxqlzgtedsmlxvd',

  // From (same Gmail)
  'from_email'  => 'kevinnhalby@gmail.com',
  'from_name'   => 'Oops Admin Bot',

  // To (where notifications go)
  'to_email'    => 'kevinhalby70199@gmail.com',
  'to_name'     => 'Kevin',
];
