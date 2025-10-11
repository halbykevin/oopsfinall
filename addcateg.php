<?php
session_start();
if (!isset($_SESSION['username'])) {
  header("Location: login.php");
  exit();
}
include 'db.php';

$flash = '';
$flash_type = '';

// ADD CATEGORY
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create') {
  $name = trim($_POST['name'] ?? '');
  if ($name !== '') {
    $stmt = $conn->prepare("INSERT INTO category (name) VALUES (?)");
    $stmt->bind_param("s", $name);
    $stmt->execute();
    $stmt->close();
    $flash = 'Category added successfully.';
    $flash_type = 'success';
  } else {
    $flash = 'Please enter a category name.';
    $flash_type = 'error';
  }
}

// UPDATE CATEGORY
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update') {
  $id = intval($_POST['id'] ?? 0);
  $name = trim($_POST['name'] ?? '');
  if ($id > 0 && $name !== '') {
    $stmt = $conn->prepare("UPDATE category SET name=? WHERE id=?");
    $stmt->bind_param("si", $name, $id);
    $stmt->execute();
    $stmt->close();
    $flash = 'Category updated.';
    $flash_type = 'success';
  }
}

// DELETE CATEGORY
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
  $id = intval($_POST['id'] ?? 0);
  if ($id > 0) {
    $stmt = $conn->prepare("DELETE FROM category WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    $flash = 'Category deleted successfully.';
    $flash_type = 'success';
  }
}

// FETCH ALL
$rows = [];
$res = $conn->query("SELECT id, name FROM category ORDER BY id DESC");
while ($r = $res->fetch_assoc()) $rows[] = $r;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Add Category | Admin</title>
<style>
  :root {
    --bg:#0f1115; --card:#1a1d23; --text:#fff; --muted:#9aa0a6;
    --primary:#0d6efd; --primary-hover:#0b5ed7;
    --danger:#d93025; --border:#2b2f36;
    --radius:16px; --shadow:0 6px 20px rgba(0,0,0,.25);
  }
  *{ box-sizing:border-box }
  body {
    margin:0; font-family:'Poppins',system-ui,-apple-system,Segoe UI,Roboto,Arial;
    background:var(--bg); color:var(--text);
    display:flex; flex-direction:column; align-items:center;
    padding:40px 20px; min-height:100vh;
  }
  .card {
    background:var(--card); border:1px solid var(--border);
    border-radius:var(--radius); padding:25px 30px;
    box-shadow:var(--shadow); width:min(90vw,700px);
  }
  h1 { text-align:center; margin:6px 0 10px; }
  .back {
    text-decoration:none; background:#6b7280; color:#fff;
    padding:10px 16px; border-radius:999px; font-size:14px;
  }
  .toolbar{ display:flex; justify-content:space-between; align-items:center; }

  .flash {
    padding:10px; border-radius:10px; text-align:center; margin:15px 0;
    font-weight:500;
  }
  .flash.success { background:#16a34a; }
  .flash.error { background:#d93025; }

  form.add-form {
    display:flex; gap:10px; margin-bottom:22px; flex-wrap:wrap;
  }
  .add-form input[type="text"]{
    flex:1; padding:12px; border-radius:10px; border:1px solid #444;
    background:#1e2229; color:#fff; outline:none;
  }
  .btn {
    border:none; border-radius:999px; padding:12px 20px;
    font-weight:600; cursor:pointer; transition:.2s;
    color:#fff;
  }
  .btn.primary { background:var(--primary); }
  .btn.primary:hover { background:var(--primary-hover); }
  .btn.danger { background:var(--danger); }
  .btn.secondary { background:#6b7280; }

  /* TABLE / LIST */
  .table{
    width:100%;
    border-collapse:collapse;
    margin-top:6px;
    table-layout: fixed;           /* ensures stable cell widths */
  }
  .table th, .table td{
    border-bottom:1px solid #333;
    padding:12px 8px;
    text-align:left;
    vertical-align: middle;
  }
  .table th{ color:var(--muted); font-weight:600; font-size:14px; }

  .row-form{
    display:flex; gap:8px; align-items:center; width:100%;
  }
  .row-input{
    flex:1 1 auto;
    min-width:0;                   /* allow shrink without overflow */
    padding:10px;
    border:1px solid #444;
    border-radius:10px;
    background:#1e2229;
    color:#fff;                    /* white text as requested */
    outline:none;
  }
  .row-actions{ display:flex; gap:8px; flex-wrap:wrap; }

  /* Mobile: turn rows into cards */
  @media (max-width: 640px){
    .card{ padding:22px; }
    .table{ border:0; }
    .table thead{ display:none; }

    .table tbody tr{
      display:block;
      background:#1a1d23;
      border:1px solid var(--border);
      border-radius:12px;
      padding:12px;
      margin-bottom:12px;
    }
    .table td{
      display:block;
      border:0;
      padding:8px 0;
    }
    .table td[data-label]::before{
      content: attr(data-label);
      display:block;
      color: var(--muted);
      font-size:12px;
      margin-bottom:6px;
    }

    /* Input + Save grid on mobile */
    .row-form{
      display:grid;
      grid-template-columns: 1fr auto;
      gap:10px;
    }

    /* Make Delete fill width nicely */
    .row-actions{
      display:grid;
      grid-template-columns: 1fr;
      gap:10px;
    }
    .row-actions .btn{ width:100%; }
  }

  /* Extra small phones */
  @media (max-width: 380px){
    .btn{ padding:11px 16px; }
  }
</style>
</head>
<body>
  <div class="card">
    <div class="toolbar">
      <a class="back" href="admin.php">← Back</a>
      <h1>Manage Categories</h1>
      <span></span>
    </div>

    <?php if($flash): ?>
      <div class="flash <?= htmlspecialchars($flash_type) ?>"><?= htmlspecialchars($flash) ?></div>
    <?php endif; ?>

    <!-- Add Category -->
    <form method="post" class="add-form" autocomplete="off">
      <input type="hidden" name="action" value="create">
      <input type="text" name="name" placeholder="Enter category name..." required>
      <button class="btn primary" type="submit">Add</button>
    </form>

    <!-- List / Edit / Delete -->
    <table class="table" id="catTable">
      <thead>
        <tr><th style="width:80px;">ID</th><th>Name</th><th style="width:220px;">Actions</th></tr>
      </thead>
      <tbody>
        <?php foreach($rows as $r): ?>
          <tr data-id="<?= (int)$r['id'] ?>">
            <td data-label="ID"><?= (int)$r['id'] ?></td>

            <td data-label="Name">
              <form method="post" class="row-form">
                <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                <input type="hidden" name="action" value="update">
                <input class="row-input" type="text" name="name" value="<?= htmlspecialchars($r['name']) ?>" required>
                <button class="btn primary" type="submit">Save</button>
              </form>
            </td>

            <td data-label="Actions">
              <div class="row-actions">
                <form method="post" onsubmit="return confirm('Delete this category?');">
                  <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                  <input type="hidden" name="action" value="delete">
                  <button class="btn danger" type="submit">Delete</button>
                </form>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</body>
</html>
