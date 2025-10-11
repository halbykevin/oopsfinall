<?php
// additem.php
session_start();
if (!isset($_SESSION['username'])) {
  header("Location: login.php");
  exit();
}
include 'db.php';

$flash = '';
$flash_type = '';

// Load all categories
$cats = [];
$rc = $conn->query("SELECT id, name FROM category ORDER BY name ASC");
while ($c = $rc->fetch_assoc()) $cats[] = $c;
$rc && $rc->close();

// Load all subcategories (for selects + validation)
$subs = [];
$rs = $conn->query("SELECT id, name, category_id FROM subcategory ORDER BY name ASC");
while ($s = $rs->fetch_assoc()) $subs[] = $s;
$rs && $rs->close();

// Helper: check subcategory exists and return its category_id
function get_subcat_category($conn, $sub_id) {
  $stmt = $conn->prepare("SELECT category_id FROM subcategory WHERE id = ?");
  $stmt->bind_param("i", $sub_id);
  $stmt->execute();

  $cid = null; // ✅ define before using
  $stmt->bind_result($cid);
  $ok = $stmt->fetch();
  $stmt->close();

  return $ok ? intval($cid) : 0;
}


// CREATE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create') {
  $category_id    = intval($_POST['category_id'] ?? 0);
  $subcategory_id = intval($_POST['subcategory_id'] ?? 0);
  $name           = trim($_POST['name'] ?? '');
  $description    = trim($_POST['description'] ?? '');
  $price          = trim($_POST['price'] ?? '');

  if ($subcategory_id <= 0 || $name === '' || $price === '') {
    $flash = 'please fill all required fields (subcategory, name, price).';
    $flash_type = 'error';
  } else {
    // Validate subcategory belongs to (or implies) category
    $sub_cat_id = get_subcat_category($conn, $subcategory_id);
    if ($sub_cat_id <= 0 || ($category_id > 0 && $category_id !== $sub_cat_id)) {
      $flash = 'selected subcategory does not match the chosen category.';
      $flash_type = 'error';
    } else {
      $price_val = floatval($price);
      $stmt = $conn->prepare("INSERT INTO item (subcategory_id, name, description, price) VALUES (?, ?, ?, ?)");
      $stmt->bind_param("issd", $subcategory_id, $name, $description, $price_val);
      $stmt->execute();
      $stmt->close();
      $flash = 'item added successfully.';
      $flash_type = 'success';
    }
  }
}

// UPDATE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update') {
  $id             = intval($_POST['id'] ?? 0);
  $category_id    = intval($_POST['category_id'] ?? 0);
  $subcategory_id = intval($_POST['subcategory_id'] ?? 0);
  $name           = trim($_POST['name'] ?? '');
  $description    = trim($_POST['description'] ?? '');
  $price          = trim($_POST['price'] ?? '');

  if ($id <= 0 || $subcategory_id <= 0 || $name === '' || $price === '') {
    $flash = 'invalid data for update.';
    $flash_type = 'error';
  } else {
    $sub_cat_id = get_subcat_category($conn, $subcategory_id);
    if ($sub_cat_id <= 0 || ($category_id > 0 && $category_id !== $sub_cat_id)) {
      $flash = 'selected subcategory does not match the chosen category.';
      $flash_type = 'error';
    } else {
      $price_val = floatval($price);
      $stmt = $conn->prepare("UPDATE item SET subcategory_id=?, name=?, description=?, price=? WHERE id=?");
      $stmt->bind_param("issdi", $subcategory_id, $name, $description, $price_val, $id);
      $stmt->execute();
      $stmt->close();
      $flash = 'item updated.';
      $flash_type = 'success';
    }
  }
}

// DELETE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
  $id = intval($_POST['id'] ?? 0);
  if ($id > 0) {
    $stmt = $conn->prepare("DELETE FROM item WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    $flash = 'item deleted.';
    $flash_type = 'success';
  }
}

// LIST (join for names)
$items = [];
$q = "SELECT i.id, i.name, i.description, i.price,
             s.id AS sub_id, s.name AS sub_name, s.category_id,
             c.id AS cat_id, c.name AS cat_name
      FROM item i
      JOIN subcategory s ON s.id = i.subcategory_id
      JOIN category c ON c.id = s.category_id
      ORDER BY i.id DESC";
$ri = $conn->query($q);
while ($r = $ri->fetch_assoc()) $items[] = $r;
$ri && $ri->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Add Item | Admin</title>
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
    width:min(94vw, 1100px);
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

  /* add form */
  .grid-form{
    display:grid;
    grid-template-columns: 1.3fr 1.3fr 1.4fr 2fr 1fr auto;
    gap:12px; margin-bottom:22px; align-items:end;
  }
  @media (max-width: 1000px){
    .grid-form{ grid-template-columns: 1fr 1fr; }
  }
  @media (max-width: 640px){
    .grid-form{ grid-template-columns: 1fr; }
  }
  label{ display:block; font-size:12px; color:var(--muted); margin-bottom:6px; }
  input[type="text"], input[type="number"], select, textarea{
    width:100%; padding:12px; border-radius:10px; border:1px solid #444;
    background:#1e2229; color:#fff; outline:none;
  }
  textarea{ resize:vertical; min-height:42px; }
  select option{ color:#fff; background:#1a1d23; }
  .btn{
    border:none; border-radius:999px; padding:12px 20px; font-weight:600; cursor:pointer; transition:.2s; color:#fff;
  }
  .btn.primary{ background:var(--primary); }
  .btn.primary:hover{ background:var(--primary-hover); }
  .btn.danger{ background:var(--danger); }
  .btn.secondary{ background:#6b7280; }

  /* table/list */
  .table{
    width:100%; border-collapse:collapse; margin-top:6px; table-layout: fixed;
  }
  .table th, .table td{
    border-bottom:1px solid #333; padding:12px 8px; text-align:left; vertical-align:middle;
  }
  .table th{ color:var(--muted); font-weight:600; font-size:14px; }

  .row-form{ display:grid; grid-template-columns: 1.1fr 1.1fr 1.2fr 1.8fr .8fr auto; gap:10px; align-items:center; }
  .row-select, .row-input, .row-textarea, .row-price{
    width:100%; padding:10px; border:1px solid #444; border-radius:10px; background:#1e2229; color:#fff;
  }
  .row-textarea{ resize:vertical; min-height:38px; }
  .row-actions{ display:flex; gap:8px; flex-wrap:wrap; justify-content:flex-end; }

  /* Mobile: card rows */
  @media (max-width: 1000px){
    .row-form{ grid-template-columns: 1fr 1fr; }
  }
  @media (max-width: 760px){
    .table{ border:0; }
    .table thead{ display:none; }
    .table tbody tr{
      display:block; background:#1a1d23; border:1px solid var(--border);
      border-radius:12px; padding:12px; margin-bottom:12px;
    }
    .table td{ display:block; border:0; padding:8px 0; }
    .table td[data-label]::before{
      content: attr(data-label);
      display:block; color:var(--muted); font-size:12px; margin-bottom:6px;
    }
    .row-form{ grid-template-columns: 1fr; }
    .row-actions{ display:grid; grid-template-columns: 1fr; }
    .row-actions .btn{ width:100%; }
  }
</style>
</head>
<body>
  <div class="card">
    <div class="toolbar">
      <a class="back" href="admin.php">← Back</a>
      <h1>Manage Items</h1>
      <span></span>
    </div>

    <?php if($flash): ?>
      <div class="flash <?= htmlspecialchars($flash_type) ?>"><?= htmlspecialchars($flash) ?></div>
    <?php endif; ?>

    <!-- Create item -->
    <form method="post" class="grid-form" autocomplete="off" id="createForm">
      <input type="hidden" name="action" value="create">

      <div>
        <label for="c_cat">category</label>
        <select id="c_cat" name="category_id" required>
          <option value="">select category…</option>
          <?php foreach ($cats as $c): ?>
            <option value="<?= (int)$c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div>
        <label for="c_sub">subcategory</label>
        <select id="c_sub" name="subcategory_id" required>
          <option value="">select subcategory…</option>
          <?php foreach ($subs as $s): ?>
            <option value="<?= (int)$s['id'] ?>" data-cat="<?= (int)$s['category_id'] ?>"><?= htmlspecialchars($s['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div>
        <label for="c_name">name</label>
        <input id="c_name" type="text" name="name" placeholder="item name" required>
      </div>

      <div>
        <label for="c_desc">description</label>
        <textarea id="c_desc" name="description" placeholder="optional description"></textarea>
      </div>

      <div>
        <label for="c_price">price</label>
        <input id="c_price" type="number" step="0.01" min="0" name="price" placeholder="0.00" required>
      </div>

      <div>
        <button class="btn primary" type="submit">Add Item</button>
      </div>
    </form>

    <!-- List / edit / delete -->
    <table class="table">
      <thead>
        <tr>
          <th style="width:70px;">ID</th>
          <th>Item</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($items as $it): ?>
          <tr data-id="<?= (int)$it['id'] ?>">
            <td data-label="ID"><?= (int)$it['id'] ?></td>
            <td data-label="Item">
              <form method="post" class="row-form">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="id" value="<?= (int)$it['id'] ?>">

                <select class="row-select js-cat">
                  <?php foreach ($cats as $c): ?>
                    <option value="<?= (int)$c['id'] ?>" <?= $c['id']==$it['cat_id'] ? 'selected' : '' ?>>
                      <?= htmlspecialchars($c['name']) ?>
                    </option>
                  <?php endforeach; ?>
                </select>

                <select class="row-select js-sub" name="subcategory_id" required>
                  <?php foreach ($subs as $s): ?>
                    <option value="<?= (int)$s['id'] ?>"
                            data-cat="<?= (int)$s['category_id'] ?>"
                            <?= $s['id']==$it['sub_id'] ? 'selected' : '' ?>>
                      <?= htmlspecialchars($s['name']) ?>
                    </option>
                  <?php endforeach; ?>
                </select>

                <input class="row-input" name="name" type="text" value="<?= htmlspecialchars($it['name']) ?>" required>

                <textarea class="row-textarea" name="description" placeholder="description…"><?= htmlspecialchars($it['description']) ?></textarea>

                <input class="row-price" name="price" type="number" step="0.01" min="0" value="<?= number_format((float)$it['price'], 2, '.', '') ?>" required>

                <div class="row-actions">
                  <button class="btn primary" type="submit">Save</button>
                </form>
                  <form method="post" onsubmit="return confirm('Delete this item?');">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= (int)$it['id'] ?>">
                    <button class="btn danger" type="submit">Delete</button>
                  </form>
                </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

<script>
// filter subcategory options to those that match selected category
function filterSubcats(catSelect, subSelect, keepCurrentIfValid = true) {
  const catId = parseInt(catSelect.value || '0', 10);
  const options = Array.from(subSelect.querySelectorAll('option'));
  const current = subSelect.value;

  // Always keep the first placeholder option if exists
  options.forEach(opt => {
    const d = parseInt(opt.getAttribute('data-cat') || '0', 10);
    if (!opt.getAttribute('data-cat')) { // placeholder
      opt.hidden = false;
      return;
    }
    opt.hidden = !(d === catId);
  });

  // If current sub no longer matches, pick first visible
  const currentValid = options.some(o => !o.hidden && o.value === current);
  if (!(keepCurrentIfValid && currentValid)) {
    const firstVisible = options.find(o => !o.hidden && o.getAttribute('data-cat'));
    subSelect.value = firstVisible ? firstVisible.value : '';
  }
}

// Setup for create form
const cCat = document.getElementById('c_cat');
const cSub = document.getElementById('c_sub');
if (cCat && cSub) {
  filterSubcats(cCat, cSub, false);
  cCat.addEventListener('change', () => filterSubcats(cCat, cSub, false));
}

// Setup for each row
document.querySelectorAll('.row-form').forEach(form => {
  const catSel = form.querySelector('.js-cat');
  const subSel = form.querySelector('.js-sub');
  if (catSel && subSel) {
    filterSubcats(catSel, subSel, true);
    catSel.addEventListener('change', () => filterSubcats(catSel, subSel, false));
    // Ensure category is sent too (for server-side consistency)
    // Create a hidden input bound to category select value
    let hid = form.querySelector('input[name="category_id"]');
    if (!hid) {
      hid = document.createElement('input');
      hid.type = 'hidden';
      hid.name = 'category_id';
      form.appendChild(hid);
    }
    const sync = () => { hid.value = catSel.value; };
    sync();
    catSel.addEventListener('change', sync);
  }
});
</script>
</body>
</html>
