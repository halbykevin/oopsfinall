<?php
session_start();
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

// logout logic
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: login.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Admin | Oopsz</title>
<style>
  :root {
    --bg:#f6f8fa;
    --card:#fff;
    --text:#222;
    --muted:#666;
    --primary:#0d6efd;
    --primary-hover:#0b5ed7;
    --border:#e6e8eb;
    --shadow:0 6px 20px rgba(0,0,0,.08);
    --radius:16px;
  }

  body {
    margin: 0;
    font-family: 'Poppins', sans-serif;
    background: var(--bg);
    color: var(--text);
    min-height: 100vh;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: flex-start;
    padding: 40px 20px;
    background-color: #222;
  }

  .admin-container {
    background: var(--card);
    border: 1px solid var(--border);
    box-shadow: var(--shadow);
    border-radius: var(--radius);
    padding: 30px 40px;
    width: min(92vw, 450px);
    display: flex;
    flex-direction: column;
    align-items: center;
    background-color: #222;
    gap: 18px;
  }

  h1 {
    font-size: clamp(20px, 2vw + 1rem, 26px);
    margin-bottom: 10px;
    text-align: center;
    color: white;
  }

  p {
    margin: 0 0 20px;
    color: var(--muted);
    font-size: 14px;
    text-align: center;
    color: white;
  }

  .bubble-btn {
    appearance: none;
    border: none;
    cursor: pointer;
    background: var(--primary);
    color: #fff;
    padding: 14px 28px;
    width: 100%;
    border-radius: 999px;
    font-size: 16px;
    font-weight: 500;
    box-shadow: 0 8px 18px rgba(13,110,253,.18);
    transition: all .2s ease;
    text-decoration: none;
    text-align: center;
  }

  .bubble-btn:hover {
    background: var(--primary-hover);
    transform: translateY(-2px);
  }

  .bubble-btn:active {
    transform: translateY(0);
  }

  .logout-btn {
    background: #d93025;
    box-shadow: 0 8px 18px rgba(217,48,37,.2);
  }

  .logout-btn:hover {
    background: #b1271e;
  }

  @media (max-width: 480px) {
    .admin-container {
      padding: 25px;
      width: 100%;
    }
    .bubble-btn {
      font-size: 15px;
      padding: 12px 20px;
    }
  }
</style>
</head>
<body>
  <div class="admin-container">
    <h1>Welcome, <?= htmlspecialchars($_SESSION['username']); ?> 👋</h1>
    <p>What would you like to do today?</p>

    <a href="addcateg.php" class="bubble-btn">Add New Category</a>
    <a href="addsubcateg.php" class="bubble-btn">Add New Sub Category</a>
    <a href="additem.php" class="bubble-btn">Add New Item</a>
    <a href="adduser.php" class="bubble-btn">Add New User</a>
    <a href="?logout=true" class="bubble-btn logout-btn">Logout</a>
  </div>
</body>
</html>
