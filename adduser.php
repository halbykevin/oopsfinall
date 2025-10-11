<?php
// adduser.php
session_start();
if (!isset($_SESSION['username'])) {
  header("Location: login.php");
  exit();
}
include 'db.php';

$flash = '';
$flash_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create') {
  $username = trim($_POST['username'] ?? '');
  $password = trim($_POST['password'] ?? '');
  $confirm  = trim($_POST['confirm'] ?? '');

  if ($username === '' || $password === '' || $confirm === '') {
    $flash = 'please fill all fields.';
    $flash_type = 'error';
  } elseif ($password !== $confirm) {
    $flash = 'passwords do not match.';
    $flash_type = 'error';
  } else {
    // optional: enforce min length
    if (strlen($username) < 3 || strlen($password) < 3) {
      $flash = 'username and password must be at least 3 characters.';
      $flash_type = 'error';
    } else {
      // check duplicate username
      $chk = $conn->prepare("select id from users where username = ? limit 1");
      $chk->bind_param("s", $username);
      $chk->execute();
      $res = $chk->get_result();
      $exists = $res && $res->num_rows > 0;
      $chk->close();

      if ($exists) {
        $flash = 'username already exists.';
        $flash_type = 'error';
      } else {
        // NOTE: for production you should hash passwords.
        // If you want hashing, replace $password with password_hash($password, PASSWORD_DEFAULT)
        $stmt = $conn->prepare("insert into users (username, password) values (?, ?)");
        $stmt->bind_param("ss", $username, $password);
        $stmt->execute();
        $stmt->close();
        $flash = 'user added successfully.';
        $flash_type = 'success';
      }
    }
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Add User | Admin</title>
<style>
  :root {
    --bg:#0f1115; --card:#1a1d23; --text:#fff; --muted:#9aa0a6;
    --primary:#0d6efd; --primary-hover:#0b5ed7;
    --danger:#d93025; --border:#2b2f36;
    --radius:16px; --shadow:0 6px 20px rgba(0,0,0,.25);
  }
  *{ box-sizing:border-box }
  body{
    margin:0; font-family:'Poppins',system-ui,-apple-system,Segoe UI,Roboto,Arial;
    background:var(--bg); color:var(--text);
    min-height:100vh; display:flex; align-items:flex-start; justify-content:center;
    padding:40px 20px;
  }
  .card{
    background:var(--card); border:1px solid var(--border);
    border-radius:var(--radius); padding:25px 30px; box-shadow:var(--shadow);
    width:min(92vw, 520px);
  }
  .toolbar{ display:flex; justify-content:space-between; align-items:center; }
  h1{ margin:6px 0 10px; text-align:center; }
  .back{
    text-decoration:none; background:#6b7280; color:#fff;
    padding:10px 16px; border-radius:999px; font-size:14px;
  }

  .flash{
    padding:10px; border-radius:10px; text-align:center; margin:15px 0; font-weight:500;
  }
  .flash.success{ background:#16a34a; }
  .flash.error{ background:#d93025; }

  .form{
    display:grid; grid-template-columns: 1fr; gap:14px; margin-top:10px;
  }
  label{ display:block; font-size:12px; color:var(--muted); margin-bottom:6px; }
  input[type="text"], input[type="password"]{
    width:100%; padding:12px; border-radius:10px; border:1px solid #444;
    background:#1e2229; color:#fff; outline:none;
  }

  .btn{
    border:none; border-radius:999px; padding:12px 20px; font-weight:600; cursor:pointer; transition:.2s; color:#fff;
    background:var(--primary); box-shadow:0 8px 18px rgba(13,110,253,.18);
  }
  .btn:hover{ background:var(--primary-hover); transform: translateY(-1px); }
  .btn:active{ transform: translateY(0); }

  .hint{ color:var(--muted); font-size:12px; text-align:center; margin-top:4px; }
</style>
</head>
<body>
  <div class="card">
    <div class="toolbar">
      <a class="back" href="admin.php">← Back</a>
      <h1>Add New User</h1>
      <span></span>
    </div>

    <?php if ($flash): ?>
      <div class="flash <?= htmlspecialchars($flash_type) ?>"><?= htmlspecialchars($flash) ?></div>
    <?php endif; ?>

    <form method="post" class="form" autocomplete="off">
      <input type="hidden" name="action" value="create">

      <div>
        <label for="u">username</label>
        <input id="u" name="username" type="text" required minlength="3" placeholder="e.g., kevin">
      </div>

      <div>
        <label for="p">password</label>
        <input id="p" name="password" type="password" required minlength="3" placeholder="••••••">
      </div>

      <div>
        <label for="c">confirm password</label>
        <input id="c" name="confirm" type="password" required minlength="3" placeholder="••••••">
      </div>

      <button class="btn" type="submit">Add User</button>
      <div class="hint">only creation is allowed here. users list/edit are disabled.</div>
    </form>
  </div>
</body>
</html>
