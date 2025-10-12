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

// --- Helpers ---
function normalize_subcat_order(mysqli $conn, int $category_id): void {
  // Lock rows of this category to avoid races during renumbering (requires InnoDB)
  $sel = $conn->prepare("
    SELECT id
    FROM subcategory
    WHERE category_id = ?
    ORDER BY COALESCE(sort_order,0) ASC, id ASC
    FOR UPDATE
  ");
  $sel->bind_param("i", $category_id);
  $sel->execute();
  $res = $sel->get_result();

  $i = 1;
  while ($row = $res->fetch_assoc()) {
    $upd = $conn->prepare("UPDATE subcategory SET sort_order=? WHERE id=?");
    $upd->bind_param("ii", $i, $row['id']);
    $upd->execute();
    $upd->close();
    $i++;
  }
  $sel->close();
}

// Load categories (keep admin-visible order)
$cats = [];
$rc = $conn->query("SELECT id, name, COALESCE(sort_order,0) AS sort_order FROM category ORDER BY sort_order ASC, id ASC");
while ($c = $rc->fetch_assoc()) { $cats[] = $c; }
$rc && $rc->close();

// --- Actions ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';

  // CREATE
  if ($action === 'create') {
    $name = trim($_POST['name'] ?? '');
    $category_id = intval($_POST['category_id'] ?? 0);

    if ($name === '' || $category_id <= 0) {
      $flash = 'please provide a name and select a category.';
      $flash_type = 'error';
    } else {
      // Next order at end
      $stmt = $conn->prepare("SELECT COALESCE(MAX(sort_order),0)+1 AS next_order FROM subcategory WHERE category_id=?");
      $stmt->bind_param("i", $category_id);
      $stmt->execute();
      $res = $stmt->get_result();
      $next = ($res && $res->num_rows) ? (int)$res->fetch_assoc()['next_order'] : 1;
      $stmt->close();

      $ins = $conn->prepare("INSERT INTO subcategory (category_id, name, sort_order) VALUES (?, ?, ?)");
      $ins->bind_param("isi", $category_id, $name, $next);
      $ins->execute();
      $ins->close();

      // Normalize to ensure clean 1..N unique order
      normalize_subcat_order($conn, $category_id);

      $flash = 'subcategory added successfully.';
      $flash_type = 'success';
    }
  }

  // UPDATE (name + parent; optional sort_order kept for compatibility but not exposed)
  if ($action === 'update') {
    $id = intval($_POST['id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $category_id = intval($_POST['category_id'] ?? 0);
    $sort_order = isset($_POST['sort_order']) ? intval($_POST['sort_order']) : null;

    if ($id > 0 && $name !== '' && $category_id > 0) {
      if ($sort_order === null) {
        $up = $conn->prepare("UPDATE subcategory SET category_id=?, name=? WHERE id=?");
        $up->bind_param("isi", $category_id, $name, $id);
      } else {
        $up = $conn->prepare("UPDATE subcategory SET category_id=?, name=?, sort_order=? WHERE id=?");
        $up->bind_param("isii", $category_id, $name, $sort_order, $id);
      }
      $up->execute();
      $up->close();

      // Keep order consistent in the new parent category
      normalize_subcat_order($conn, $category_id);

      $flash = 'subcategory updated.';
      $flash_type = 'success';
    } else {
      $flash = 'invalid data for update.';
      $flash_type = 'error';
    }
  }

  // MOVE (up/down within same parent) using normalized contiguous order
  if ($action === 'move_up' || $action === 'move_down') {
    $id = intval($_POST['id'] ?? 0);
    if ($id > 0) {
      // Get current row
      $st = $conn->prepare("SELECT id, category_id, COALESCE(sort_order,0) AS sort_order FROM subcategory WHERE id=?");
      $st->bind_param("i", $id);
      $st->execute();
      $cur = $st->get_result()->fetch_assoc();
      $st->close();

      if ($cur) {
        $catId = (int)$cur['category_id'];
        // Ensure clean 1..N first
        normalize_subcat_order($conn, $catId);

        // Reload order after normalization
        $st = $conn->prepare("SELECT COALESCE(sort_order,0) AS sort_order FROM subcategory WHERE id=?");
        $st->bind_param("i", $id);
        $st->execute();
        $curOrder = (int)$st->get_result()->fetch_assoc()['sort_order'];
        $st->close();

        $targetOrder = ($action === 'move_up') ? $curOrder - 1 : $curOrder + 1;

        // Find neighbor at target order
        $q = $conn->prepare("
          SELECT id FROM subcategory
          WHERE category_id=? AND sort_order=?
          LIMIT 1
        ");
        $q->bind_param("ii", $catId, $targetOrder);
        $q->execute();
        $nb = $q->get_result()->fetch_assoc();
        $q->close();

        if ($nb) {
          $nbId = (int)$nb['id'];

          // Swap using temp value
          $tmp = -999999;

          $u = $conn->prepare("UPDATE subcategory SET sort_order=? WHERE id=?");
          // Move current out of the way
          $u->bind_param("ii", $tmp, $id);
          $u->execute();

          // Move neighbor into current slot
          $u->bind_param("ii", $curOrder, $nbId);
          $u->execute();

          // Move current into neighbor slot
          $u->bind_param("ii", $targetOrder, $id);
          $u->execute();
          $u->close();

          $flash = 'order updated.';
          $flash_type = 'success';
        }
      }
    }
  }

  // DELETE
  if ($action === 'delete') {
    $id = intval($_POST['id'] ?? 0);
    if ($id > 0) {
      // Get category_id of the row to delete (so we can normalize after)
      $g = $conn->prepare("SELECT category_id FROM subcategory WHERE id=?");
      $g->bind_param("i", $id);
      $g->execute();
      $gr = $g->get_result()->fetch_assoc();
      $g->close();

      $del = $conn->prepare("DELETE FROM subcategory WHERE id=?");
      $del->bind_param("i", $id);
      $del->execute();
      $del->close();

      if ($gr) {
        normalize_subcat_order($conn, (int)$gr['category_id']);
      }

      $flash = 'subcategory deleted successfully.';
      $flash_type = 'success';
    }
  }
}

// LIST (ordered by parent, then subcategory order/id)
$rows = [];
$sql = "
  SELECT s.id, s.name, s.category_id, COALESCE(s.sort_order,0) AS sort_order,
         c.name AS category_name, COALESCE(c.sort_order,0) AS c_order, c.id AS c_id
  FROM subcategory s
  JOIN category c ON c.id = s.category_id
  ORDER BY c_order ASC, c.id ASC, s.sort_order ASC, s.id ASC
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

  .row-input{ width:100%; min-width:0; padding:10px; border:1px solid #444; border-radius:10px; background:#1e2229; color:#fff; }
  .row-select{ width:220px; padding:10px; border:1px solid #444; border-radius:10px; background:#1e2229; color:#fff; }

  /* Arrow controls */
  .arrows{ display:flex; gap:6px; align-items:center; justify-content:flex-start; }
  .arrow-btn{
    background:#2c313a; border:1px solid #3a3f48; color:#fff;
    width:38px; height:38px; border-radius:10px; cursor:pointer;
    display:flex; align-items:center; justify-content:center;
    transition:transform .08s ease, background .15s ease;
  }
  .arrow-btn:hover{ background:#3a3f48; }
  .arrow-btn:active{ transform:translateY(1px); }
  .arrow-btn small{ font-size:16px; line-height:1; }

  .row-actions{ display:flex; gap:8px; flex-wrap:wrap; }

  @media (max-width: 760px){
    .table{ border:0; }
    .table thead{ display:none; }
    .table tbody tr{ display:block; background:#1a1d23; border:1px solid var(--border); border-radius:12px; padding:12px; margin-bottom:12px; }
    .table td{ display:block; border:0; padding:8px 0; }
    .table td[data-label]::before{ content: attr(data-label); display:block; color: var(--muted); font-size:12px; margin-bottom:6px; }
    .row-select{ width:100%; }
    .arrows{ gap:10px; }
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

    <!-- List / edit / move / delete -->
    <table class="table">
      <thead>
        <tr>
          <th style="width:70px;">ID</th>
          <th>Subcategory</th>
          <th style="width:260px;">Parent</th>
          <th style="width:160px;">Order</th>
          <th style="width:220px;">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $r): ?>
          <!-- Row-scoped form container (empty). Inputs/buttons below reference this via form="f{id}" -->
          <form id="f<?= (int)$r['id'] ?>" method="post"></form>

          <tr data-id="<?= (int)$r['id'] ?>">
            <td data-label="ID"><?= (int)$r['id'] ?></td>

            <td data-label="Subcategory">
              <input type="hidden" name="id" value="<?= (int)$r['id'] ?>" form="f<?= (int)$r['id'] ?>">
              <input class="row-input" name="name" type="text" value="<?= htmlspecialchars($r['name']) ?>" required form="f<?= (int)$r['id'] ?>">
            </td>

            <td data-label="Parent">
              <select class="row-select" name="category_id" required form="f<?= (int)$r['id'] ?>">
                <?php foreach ($cats as $c): ?>
                  <option value="<?= (int)$c['id'] ?>" <?= ($c['id'] == $r['category_id']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($c['name']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </td>

            <td data-label="Order">
              <div class="arrows">
                <button type="submit" class="arrow-btn" title="Move up"
                        name="action" value="move_up" form="f<?= (int)$r['id'] ?>"><small>▲</small></button>
                <button type="submit" class="arrow-btn" title="Move down"
                        name="action" value="move_down" form="f<?= (int)$r['id'] ?>"><small>▼</small></button>
              </div>
            </td>

            <td data-label="Actions">
              <div class="row-actions">
                <button class="btn primary" type="submit" name="action" value="update" form="f<?= (int)$r['id'] ?>">Save</button>
                <button class="btn danger" type="submit" name="action" value="delete"
                        form="f<?= (int)$r['id'] ?>"
                        onclick="return confirm('Delete this subcategory?');">Delete</button>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</body>
</html>
