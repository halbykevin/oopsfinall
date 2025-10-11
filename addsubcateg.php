<?php
// addsubcateg.php
session_start();
if (!isset($_SESSION['username'])) {
  header("Location: login.php");
  exit();
}
include 'db.php';

$flash = '';
$flash_type = '';

// load categories (keep admin-visible order)
$cats = [];
$rc = $conn->query("select id, name, coalesce(sort_order,0) as sort_order from category order by sort_order asc, id asc");
while ($c = $rc->fetch_assoc()) { $cats[] = $c; }
$rc && $rc->close();

// CREATE (auto-append at end within the chosen category)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create') {
  $name = trim($_POST['name'] ?? '');
  $category_id = intval($_POST['category_id'] ?? 0);

  if ($name === '' || $category_id <= 0) {
    $flash = 'please provide a name and select a category.';
    $flash_type = 'error';
  } else {
    // next order inside this parent category
    $stmt = $conn->prepare("select coalesce(max(sort_order),0)+1 as next_order from subcategory where category_id=?");
    $stmt->bind_param("i", $category_id);
    $stmt->execute();
    $next = ($stmt->get_result()->fetch_assoc()['next_order']) ?? 1;
    $stmt->close();

    $ins = $conn->prepare("insert into subcategory (category_id, name, sort_order) values (?, ?, ?)");
    $ins->bind_param("isi", $category_id, $name, $next);
    $ins->execute();
    $ins->close();

    $flash = 'subcategory added successfully.';
    $flash_type = 'success';
  }
}

// UPDATE (name + parent + order)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update') {
  $id = intval($_POST['id'] ?? 0);
  $name = trim($_POST['name'] ?? '');
  $category_id = intval($_POST['category_id'] ?? 0);
  $sort_order = intval($_POST['sort_order'] ?? 0);

  if ($id > 0 && $name !== '' && $category_id > 0) {
    $up = $conn->prepare("update subcategory set category_id=?, name=?, sort_order=? where id=?");
    $up->bind_param("isii", $category_id, $name, $sort_order, $id);
    $up->execute();
    $up->close();

    $flash = 'subcategory updated.';
    $flash_type = 'success';
  } else {
    $flash = 'invalid data for update.';
    $flash_type = 'error';
  }
}

// DELETE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
  $id = intval($_POST['id'] ?? 0);
  if ($id > 0) {
    $del = $conn->prepare("delete from subcategory where id=?");
    $del->bind_param("i", $id);
    $del->execute();
    $del->close();
    $flash = 'subcategory deleted successfully.';
    $flash_type = 'success';
  }
}

// LIST (ordered by parent category, then subcategory order)
$rows = [];
$sql = "
  select s.id, s.name, s.category_id, coalesce(s.sort_order,0) as sort_order,
         c.name as category_name, coalesce(c.sort_order,0) as c_order
  from subcategory s
  join category c on c.id = s.category_id
  order by c_order asc, c.id asc, s.sort_order asc, s.id asc
";
$rs = $conn->query($sql);
while ($r = $rs->fetch_assoc()) { $rows[] = $r; }
$rs && $rs->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Add Subcategory | Admin</title>
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
    width:min(92vw, 900px);
  }
  .toolbar{ display:flex; justify-content:space-between; align-items:center; }
  h1{ margin:6px 0 10px; text-align:center; }
  .back{
    text-decoration:none; background:#6b7280; color:#fff;
    padding:10px 16px; border-radius:999px; font-size:14px;
  }

  .flash{ padding:10px; border-radius:10px; text-align:center; margin:15px 0; font-weight:500; }
  .flash.success{ background:#16a34a; }
  .flash.error{ background:#d93025; }

  .grid-form{ display:grid; grid-template-columns: 2fr 2fr auto; gap:12px; margin-bottom:22px; }
  @media (max-width: 700px){ .grid-form{ grid-template-columns: 1fr; } }

  input[type="text"], select{
    width:100%; padding:12px; border-radius:10px; border:1px solid #444;
    background:#1e2229; color:#fff; outline:none;
  }
  select option{ color:#fff; background:#1a1d23; }

  .btn{ border:none; border-radius:999px; padding:12px 20px; font-weight:600; cursor:pointer; transition:.2s; color:#fff; }
  .btn.primary{ background:var(--primary); }
  .btn.primary:hover{ background:var(--primary-hover); }
  .btn.danger{ background:var(--danger); }
  .btn.secondary{ background:#6b7280; }

  .table{ width:100%; border-collapse:collapse; margin-top:6px; table-layout: fixed; }
  .table th, .table td{ border-bottom:1px solid #333; padding:12px 8px; text-align:left; vertical-align:middle; }
  .table th{ color:var(--muted); font-weight:600; font-size:14px; }

  .row-form{ display:flex; gap:8px; align-items:center; width:100%; }
  .row-input{ flex:1 1 auto; min-width:0; padding:10px; border:1px solid #444; border-radius:10px; background:#1e2229; color:#fff; }
  .row-select{ width:220px; padding:10px; border:1px solid #444; border-radius:10px; background:#1e2229; color:#fff; }
  .order-input{ width:90px; text-align:center; padding:10px; border:1px solid #444; border-radius:10px; background:#1e2229; color:#fff; }
  .row-actions{ display:flex; gap:8px; flex-wrap:wrap; }

  @media (max-width: 760px){
    .table{ border:0; }
    .table thead{ display:none; }
    .table tbody tr{ display:block; background:#1a1d23; border:1px solid var(--border); border-radius:12px; padding:12px; margin-bottom:12px; }
    .table td{ display:block; border:0; padding:8px 0; }
    .table td[data-label]::before{ content: attr(data-label); display:block; color: var(--muted); font-size:12px; margin-bottom:6px; }
    .row-form{ display:grid; grid-template-columns: 1fr; gap:10px; }
    .row-select, .order-input{ width:100%; }
    .row-actions{ display:grid; grid-template-columns: 1fr; gap:10px; }
    .row-actions .btn{ width:100%; }
  }
</style>
</head>
<body>
  <div class="card">
    <div class="toolbar">
      <a class="back" href="admin.php">← Back</a>
      <h1>Manage Subcategories</h1>
      <span></span>
    </div>

    <?php if($flash): ?>
      <div class="flash <?= htmlspecialchars($flash_type) ?>"><?= htmlspecialchars($flash) ?></div>
    <?php endif; ?>

    <!-- Create subcategory -->
    <form method="post" class="grid-form" autocomplete="off">
      <input type="hidden" name="action" value="create">
      <div>
        <input type="text" name="name" placeholder="enter subcategory name..." required>
      </div>
      <div>
        <select name="category_id" required>
          <option value="">select parent category…</option>
          <?php foreach ($cats as $c): ?>
            <option value="<?= (int)$c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <button class="btn primary" type="submit">Add</button>
      </div>
    </form>

    <!-- List / edit / delete -->
    <table class="table">
      <thead>
        <tr>
          <th style="width:70px;">ID</th>
          <th>Subcategory</th>
          <th style="width:260px;">Parent</th>
          <th style="width:110px;">Order</th>
          <th style="width:200px;">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $r): ?>
          <tr data-id="<?= (int)$r['id'] ?>">
            <td data-label="ID"><?= (int)$r['id'] ?></td>

            <td data-label="Subcategory">
              <form method="post" class="row-form">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                <input class="row-input" name="name" type="text" value="<?= htmlspecialchars($r['name']) ?>" required>
            </td>

            <td data-label="Parent">
                <select class="row-select" name="category_id" required>
                  <?php foreach ($cats as $c): ?>
                    <option value="<?= (int)$c['id'] ?>" <?= ($c['id'] == $r['category_id']) ? 'selected' : '' ?>>
                      <?= htmlspecialchars($c['name']) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
            </td>

            <td data-label="Order">
              <input class="order-input" type="number" name="sort_order"
                     value="<?= (int)$r['sort_order'] ?>" min="0" step="1"
                     title="lower appears first within its category">
            </td>

            <td data-label="Actions">
              <div class="row-actions">
                <button class="btn primary" type="submit">Save</button>
              </form>
                <form method="post" onsubmit="return confirm('Delete this subcategory?');">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
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
