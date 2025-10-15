<?php
// feedback.php
session_start();
require_once __DIR__ . '/db.php';

// Helper to best-guess client IP
function client_ip(): string {
    foreach (['HTTP_CF_CONNECTING_IP','HTTP_X_FORWARDED_FOR','HTTP_X_REAL_IP','REMOTE_ADDR'] as $k) {
        if (!empty($_SERVER[$k])) {
            $v = $_SERVER[$k];
            if ($k === 'HTTP_X_FORWARDED_FOR') {
                $parts = explode(',', $v);
                return trim($parts[0]);
            }
            return trim($v);
        }
    }
    return '';
}

// Handle submit
$ok = null; $msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fname = trim($_POST['fname'] ?? '');
    $lname = trim($_POST['lname'] ?? '');
    $dob   = trim($_POST['dateofbirth'] ?? '');
    $phone = trim($_POST['number'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $exp   = (int)($_POST['experience'] ?? 0);
    $serv  = (int)($_POST['service'] ?? 0);
    $food  = (int)($_POST['food'] ?? 0);
    $atm   = (int)($_POST['atmosphere'] ?? 0);
    $text1 = trim($_POST['text1'] ?? '');
    $text2 = trim($_POST['text2'] ?? '');

    $errs = [];
    if ($fname === '') $errs[] = 'First name is required.';
    if ($lname === '') $errs[] = 'Last name is required.';
    if ($dob === '')   $errs[] = 'Date of birth is required.';
    if ($phone === '') $errs[] = 'Phone number is required.';
    if ($exp  < 1 || $exp  > 5) $errs[] = 'Please rate Overall Experience (1–5).';
    if ($food < 1 || $food > 5) $errs[] = 'Please rate Food (1–5).';
    if ($serv < 1 || $serv > 5) $errs[] = 'Please rate Service (1–5).';
    if ($atm  < 1 || $atm  > 5) $errs[] = 'Please rate Atmosphere (1–5).';
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) $errs[] = 'Email format is invalid.';

    if (!$errs) {
        $stmt = $conn->prepare("
            INSERT INTO feedback
                (fname, lname, dateofbirth, number, email, experience, service, atmosphere, food, text1, text2, feedback_date, created_at)
            VALUES
                (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURDATE(), NOW())
        ");
        $stmt->bind_param(
            "sssssiiiiss",
            $fname, $lname, $dob, $phone, $email, $exp, $serv, $atm, $food, $text1, $text2
        );
        $ok = $stmt->execute();
        $stmt->close();

        if ($ok) {
            // Send notification email (non-blocking)
            require_once __DIR__ . '/mail_send.php';
            try {
                $submitted_at = (new DateTime('now', new DateTimeZone('Asia/Beirut')))->format('Y-m-d H:i:s') . ' Asia/Beirut';
                $payload = [
                    'fname'        => $fname,
                    'lname'        => $lname,
                    'dateofbirth'  => $dob,
                    'number'       => $phone,
                    'email'        => $email,
                    'experience'   => $exp,
                    'food'         => $food,
                    'service'      => $serv,
                    'atmosphere'   => $atm,
                    'text1'        => $text1,
                    'text2'        => $text2,
                    'submitted_at' => $submitted_at,
                    'ip'           => client_ip(),
                    'user_agent'   => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 220),
                ];
                @send_feedback_email($payload);
            } catch (Throwable $e) {
                error_log('MAIL EXCEPTION (feedback): ' . $e->getMessage());
            }

            // Redirect to thank-you page
            header("Location: feedbackdone.php");
            exit;
        } else {
            $msg = 'There was an error saving your feedback. Please try again.';
            error_log('FEEDBACK INSERT ERROR: ' . $conn->error);
        }
    } else {
        $ok = false;
        $msg = implode(' ', $errs);
    }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width,initial-scale=1" />
<title>Share Your Experience</title>

<style>
@font-face{ font-family:"Montserrat"; src:url("fonts/Montserrat-Light.ttf") format("truetype"); font-weight:300; }
@font-face{ font-family:"Montserrat"; src:url("fonts/Montserrat-Bold.ttf")  format("truetype"); font-weight:700; }

:root{ --brand:#9E3722; --bg:#F3EBDF; --text:#9E3722; }

*{box-sizing:border-box;margin:0;padding:0}
html,body{height:100%}
body{
  background:var(--bg); color:var(--text);
  font-family:"Montserrat",sans-serif;
  display:flex; justify-content:center; align-items:flex-start;
  min-height:100vh; padding-top:60px; position:relative;
}

/* background ornaments */
body::before, body::after{
  content:""; position:absolute; width:300px; height:300px;
  background:url('images/icons/emblem_form_p1.svg') no-repeat center/contain;
  opacity:.08; pointer-events:none;
}
body::before{top:40px;left:40px;}
body::after{bottom:40px;right:40px;transform:rotate(20deg);}

form{
  width:min(90vw,420px);
  display:flex; flex-direction:column; align-items:center;
  gap:18px; z-index:1; position:relative;
}

/* Headings */
.hero{
  text-align:center; text-transform:uppercase; letter-spacing:.3px;
  line-height:1.2; font-weight:300; font-size:28px; margin-bottom:4px;
}
.hero span{ display:block; font-weight:700; }
.hr{ width:120px; height:2px; background:rgba(158,55,34,.35); border-radius:2px; margin:10px auto 6px; }
.sub{ text-align:center; font-weight:700; font-size:13px; text-transform:uppercase; max-width:300px; }

/* Fields */
.field{ display:flex; flex-direction:column; width:100%; }
.label{
  font-weight:700; text-transform:uppercase; font-size:13px;
  text-align:left; margin-left:8px; margin-bottom:4px;
}
.input,.textarea{
  border:2px solid rgba(158,55,34,.3); background:#fff8f0;
  border-radius:999px; padding:12px 16px;
  font-weight:700; color:var(--brand); font-size:14px;
  outline:none; transition:border-color .2s,box-shadow .2s,background .2s;
}
.textarea{ border-radius:14px; resize:vertical; min-height:90px; }
.input::placeholder,.textarea::placeholder{ color:#c87a6b; opacity:.75; font-weight:700; }
.input:focus,.textarea:focus{ border-color:var(--brand); box-shadow:0 0 0 3px rgba(158,55,34,.16); background:#fff; }

/* Stars */
.stars{ display:flex; justify-content:center; gap:6px; font-size:26px; line-height:1; }
.star{ cursor:pointer; user-select:none; transition:transform .08s ease; position:relative; }
.star:hover{ transform:scale(1.15); }
.star::before{ content:'☆'; }
.star.active::before{ content:'★'; }

/* Submit */
.btn{
  background:var(--brand); color:#fff; border:none; border-radius:999px;
  padding:14px 24px; font-weight:700; font-size:15px; cursor:pointer;
  box-shadow:0 10px 22px rgba(158,55,34,.25); transition:transform .15s,box-shadow .15s;
}
.btn:hover{ transform:translateY(-1px); box-shadow:0 14px 28px rgba(158,55,34,.35); }
.btn:active{ transform:translateY(0); }

/* Message */
.alert{
  text-align:center; background:#fff8f0; border:2px solid rgba(158,55,34,.25);
  border-radius:12px; padding:10px; font-weight:700; color:var(--brand); font-size:13px;
}
.legend{ font-size:12px; opacity:.7; margin-top: 2px; text-align:center; }
</style>
</head>
<body>
<form method="post" action="">
  <h1 class="hero">Share your<br><span>Experience</span></h1>
  <div class="hr"></div>
  <div class="sub">Thank you for dining with us! Please share your feedback.</div>

  <?php if ($msg !== ''): ?><div class="alert"><?= htmlspecialchars($msg) ?></div><?php endif; ?>

  <!-- FIRST NAME -->
  <div class="field">
    <label class="label">First Name</label>
    <input class="input" name="fname" type="text" placeholder="Type..." value="<?= isset($fname)?htmlspecialchars($fname):'' ?>" required>
  </div>

  <!-- LAST NAME -->
  <div class="field">
    <label class="label">Last Name</label>
    <input class="input" name="lname" type="text" placeholder="Type..." value="<?= isset($lname)?htmlspecialchars($lname):'' ?>" required>
  </div>

  <!-- DOB -->
  <div class="field">
    <label class="label">Date of Birth</label>
    <input class="input" name="dateofbirth" type="date" value="<?= isset($dob)?htmlspecialchars($dob):'' ?>" required>
  </div>

  <!-- PHONE -->
  <div class="field">
    <label class="label">Phone Number</label>
    <input class="input" name="number" type="tel" placeholder="Type..." value="<?= isset($phone)?htmlspecialchars($phone):'' ?>" required>
  </div>

  <!-- EMAIL -->
  <div class="field">
    <label class="label">Email (Optional)</label>
    <input class="input" name="email" type="email" placeholder="Type..." value="<?= isset($email)?htmlspecialchars($email):'' ?>">
    <div class="legend">We’ll use this only to follow up if needed.</div>
  </div>

  <!-- Ratings -->
  <div class="field">
    <label class="label">Overall Experience</label>
    <div class="stars" data-name="experience">
      <?php for ($i=1; $i<=5; $i++): ?>
        <span class="star" data-v="<?= $i ?>"></span>
      <?php endfor; ?>
      <input type="hidden" name="experience" value="<?= isset($exp)?(int)$exp:0 ?>">
    </div>
  </div>

  <div class="field">
    <label class="label">How was your Food?</label>
    <div class="stars" data-name="food">
      <?php for ($i=1; $i<=5; $i++): ?>
        <span class="star" data-v="<?= $i ?>"></span>
      <?php endfor; ?>
      <input type="hidden" name="food" value="<?= isset($food)?(int)$food:0 ?>">
    </div>
  </div>

  <div class="field">
    <label class="label">How was our Service?</label>
    <div class="stars" data-name="service">
      <?php for ($i=1; $i<=5; $i++): ?>
        <span class="star" data-v="<?= $i ?>"></span>
      <?php endfor; ?>
      <input type="hidden" name="service" value="<?= isset($serv)?(int)$serv:0 ?>">
    </div>
  </div>

  <div class="field">
    <label class="label">How was the Atmosphere?</label>
    <div class="stars" data-name="atmosphere">
      <?php for ($i=1; $i<=5; $i++): ?>
        <span class="star" data-v="<?= $i ?>"></span>
      <?php endfor; ?>
      <input type="hidden" name="atmosphere" value="<?= isset($atm)?(int)$atm:0 ?>">
    </div>
  </div>

  <!-- Feedback Text -->
  <div class="field">
    <label class="label">What’s one thing we should definitely keep doing?</label>
    <textarea class="textarea" name="text1" placeholder="Type..."><?= isset($text1)?htmlspecialchars($text1):'' ?></textarea>
  </div>

  <div class="field">
    <label class="label">What’s something we should change?</label>
    <textarea class="textarea" name="text2" placeholder="Type..."><?= isset($text2)?htmlspecialchars($text2):'' ?></textarea>
  </div>

  <button class="btn" type="submit">Submit</button><br>
</form>

<script>
// Stars: fill on hover/click, store in hidden input
document.querySelectorAll('.stars').forEach(group=>{
  const hidden = group.querySelector('input[type="hidden"]');
  let current = Number(hidden.value||0);

  const apply = (val)=>{
    group.querySelectorAll('.star').forEach(st=>{
      st.classList.toggle('active', Number(st.dataset.v) <= val);
    });
  };
  apply(current);

  group.querySelectorAll('.star').forEach(st=>{
    st.addEventListener('mouseenter', ()=>apply(Number(st.dataset.v)));
    st.addEventListener('mouseleave', ()=>apply(current));
    st.addEventListener('click', ()=>{
      current = Number(st.dataset.v);
      hidden.value = current;
      apply(current);
    });
  });
});
</script>
</body>
</html>
