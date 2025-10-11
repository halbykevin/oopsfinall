<?php
session_start();
include 'db.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    $sql = "SELECT * FROM users WHERE username = ? AND password = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $username, $password);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows === 1) {
        $_SESSION['username'] = $username;
        header("Location: admin.php");
        exit();
    } else {
        $error = "Invalid username or password!";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Login | Oopsz Admin</title>
<style>
  :root{
    --bg:#f6f8fa;
    --card:#fff;
    --text:#222;
    --muted:#666;
    --primary:#0d6efd;
    --primary-hover:#0b5ed7;
    --danger:#d93025;
    --border:#e6e8eb;
    --shadow:0 6px 20px rgba(0,0,0,.08);
    --radius:16px;
    --gap:14px;
  }
  @media (prefers-color-scheme: dark){
    :root{
      --bg:#0f1115;
      --card:#14171c;
      --text:#f4f6f8;
      --muted:#aeb4bd;
      --border:#21262d;
      --shadow:0 6px 20px rgba(0,0,0,.35);
    }
  }

  * { box-sizing: border-box; }

  body{
    margin:0;
    font-family: system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, "Noto Sans", "Apple Color Emoji","Segoe UI Emoji", "Segoe UI Symbol";
    background: var(--bg);
    color: var(--text);
    min-height:100dvh;
    display:grid;
    place-items:center;
    padding: clamp(16px, 3vw, 32px);
  }

  .login-wrap{
    width: min(92vw, 380px);
  }

  .login-card{
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    box-shadow: var(--shadow);
    padding: clamp(22px, 4vw, 32px);
    display:flex;
    flex-direction:column;
    gap: var(--gap);
  }

  .brand{
    display:flex;
    flex-direction:column;
    gap:6px;
    text-align:center;
  }
  .brand h1{
    font-size: clamp(18px, 2.6vw + 1rem, 24px);
    margin:0;
    font-weight: 650;
    letter-spacing:.2px;
  }
  .brand p{
    margin:0;
    color: var(--muted);
    font-size: clamp(12px, 1vw + .6rem, 14px);
  }

  .field{
    display:flex;
    flex-direction:column;
    gap:8px;
  }
  label{
    font-size: clamp(12px, .8vw + .55rem, 14px);
    color: var(--muted);
  }
  input{
    width:100%;
    padding: clamp(12px, 1.5vw + 8px, 14px);
    border:1px solid var(--border);
    border-radius:10px;
    background: transparent;
    color: inherit;
    font-size: clamp(14px, 1vw + .6rem, 16px);
    transition: border-color .2s, box-shadow .2s;
  }
  input:focus{
    outline:none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px color-mix(in srgb, var(--primary) 20%, transparent);
  }

  .password-row{
    display:grid;
    grid-template-columns: 1fr auto;
    gap:10px;
    align-items:center;
  }
  .toggle{
    border:1px solid var(--border);
    background: transparent;
    color: var(--muted);
    border-radius: 999px;
    padding: 10px 14px;
    font-size: 13px;
    cursor:pointer;
  }

  .bubble-btn{
    appearance:none;
    -webkit-appearance:none;
    border:none;
    cursor:pointer;
    display:inline-flex;
    align-items:center;
    justify-content:center;
    width:100%;
    padding: clamp(12px, 1.6vw + 10px, 14px);
    border-radius: 999px;
    font-weight: 600;
    font-size: clamp(14px, 1vw + .6rem, 16px);
    background: var(--primary);
    color:#fff;
    transition: transform .2s ease, background .2s ease, box-shadow .2s ease;
    box-shadow: 0 8px 18px rgba(13,110,253,.18);
  }
  .bubble-btn:hover{ background: var(--primary-hover); transform: translateY(-1px); }
  .bubble-btn:active{ transform: translateY(0); }
  .bubble-btn:disabled{ opacity:.6; cursor:not-allowed; transform:none; }

  .error{
    color: var(--danger);
    font-size: clamp(12px, .9vw + .55rem, 14px);
    text-align:center;
  }

  .helper{
    display:flex;
    justify-content:center;
    gap:10px;
    color: var(--muted);
    font-size: 12px;
  }

  @media (prefers-reduced-motion: reduce){
    .bubble-btn, input{ transition: none; }
  }
</style>
</head>
<body>
  <div class="login-wrap">
    <form method="post" class="login-card" novalidate id="loginForm">
      <div class="brand">
        <h1>Oops Admin</h1>
        <p>Please sign in to continue</p>
      </div>

      <div class="field">
        <label for="username">Username</label>
        <input id="username" name="username" type="text" inputmode="text" autocomplete="username" required />
      </div>

      <div class="field">
        <label for="password">Password</label>
        <div class="password-row">
          <input id="password" name="password" type="password" autocomplete="current-password" required />
          <button class="toggle" type="button" id="togglePwd" aria-label="Show password">show</button>
        </div>
      </div>

      <button type="submit" class="bubble-btn" id="submitBtn">Login</button>

      <?php if ($error): ?>
        <div class="error"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <div class="helper">
        <span></span>
      </div>
    </form>
  </div>

<script>
  // Show/Hide password
  const pwd = document.getElementById('password');
  const toggle = document.getElementById('togglePwd');
  toggle.addEventListener('click', () => {
    const showing = pwd.type === 'text';
    pwd.type = showing ? 'password' : 'text';
    toggle.textContent = showing ? 'show' : 'hide';
  });

  // Prevent double submit on slow networks
  const form = document.getElementById('loginForm');
  const submitBtn = document.getElementById('submitBtn');
  form.addEventListener('submit', () => {
    submitBtn.disabled = true;
    submitBtn.textContent = 'Signing in...';
  });

  // Focus username on load for faster login
  window.addEventListener('DOMContentLoaded', () => {
    const u = document.getElementById('username');
    if (u) u.focus();
  });
</script>
</body>
</html>
