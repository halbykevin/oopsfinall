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

// ---------- Helpers ----------
function normalize_item_order(mysqli $conn, int $subcategory_id): void {
  // Renumber 1..N within a subcategory (use FOR UPDATE to avoid races)
  $sel = $conn->prepare("
    SELECT id
    FROM item
    WHERE subcategory_id=?
    ORDER BY COALESCE(sort_order,0) ASC, id ASC
    FOR UPDATE
  ");
  $sel->bind_param("i", $subcategory_id);
  $sel->execute();
  $res = $sel->get_result();

  $i = 1;
  while ($row = $res->fetch_assoc()) {
    $upd = $conn->prepare("UPDATE item SET sort_order=? WHERE id=?");
    $upd->bind_param("ii", $i, $row['id']);
    $upd->execute();
    $upd->close();
    $i++;
  }
  $sel->close();
}

function get_subcat_category(mysqli $conn, int $sub_id): int {
  $stmt = $conn->prepare("SELECT category_id FROM subcategory WHERE id = ?");
  $stmt->bind_param("i", $sub_id);
  $stmt->execute();
  $stmt->bind_result($cid);
  $ok = $stmt->fetch();
  $stmt->close();
  return $ok ? (int)$cid : 0;
}

// ---------- Load base data ----------
$cats = [];
$rc = $conn->query("SELECT id, name, COALESCE(sort_order,0) AS sort_order FROM category ORDER BY sort_order ASC, id ASC");
while ($c = $rc->fetch_assoc()) $cats[] = $c;
$rc && $rc->close();

$subs = [];
$rs = $conn->query("SELECT id, name, category_id, COALESCE(sort_order,0) AS sort_order FROM subcategory ORDER BY sort_order ASC, id ASC");
while ($s = $rs->fetch_assoc()) $subs[] = $s;
$rs && $rs->close();

// ---------- Actions ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';

  // CREATE
  if ($action === 'create') {
    $category_id    = intval($_POST['category_id'] ?? 0);
    $subcategory_id = intval($_POST['subcategory_id'] ?? 0);
    $name           = trim($_POST['name'] ?? '');
    $description    = trim($_POST['description'] ?? '');
    $price          = trim($_POST['price'] ?? '');

    if ($subcategory_id <= 0 || $name === '' || $price === '') {
      $flash = 'please fill all required fields (subcategory, name, price).';
      $flash_type = 'error';
    } else {
      $sub_cat_id = get_subcat_category($conn, $subcategory_id);
      if ($sub_cat_id <= 0 || ($category_id > 0 && $category_id !== $sub_cat_id)) {
        $flash = 'selected subcategory does not match the chosen category.';
        $flash_type = 'error';
      } else {
        // next order at end of this subcategory
        $stmt = $conn->prepare("SELECT COALESCE(MAX(sort_order),0)+1 AS next_order FROM item WHERE subcategory_id=?");
        $stmt->bind_param("i", $subcategory_id);
        $stmt->execute();
        $res = $stmt->get_result();
        $next = ($res && $res->num_rows) ? (int)$res->fetch_assoc()['next_order'] : 1;
        $stmt->close();

        $price_val = floatval($price);

        $ins = $conn->prepare("
          INSERT INTO item (subcategory_id, name, description, price, sort_order)
          VALUES (?, ?, ?, ?, ?)
        ");
        $ins->bind_param("issdi", $subcategory_id, $name, $description, $price_val, $next);
        $ins->execute();
        $ins->close();

        // keep subcategory ordering clean
        normalize_item_order($conn, $subcategory_id);

        $flash = 'item added successfully.';
        $flash_type = 'success';
      }
    }
  }

  // UPDATE (and possibly move to another subcategory)
  if ($action === 'update') {
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
      // fetch old subcategory for normalization if changed
      $g = $conn->prepare("SELECT subcategory_id FROM item WHERE id=?");
      $g->bind_param("i", $id);
      $g->execute();
      $oldRow = $g->get_result()->fetch_assoc();
      $g->close();
      $oldSub = $oldRow ? (int)$oldRow['subcategory_id'] : 0;

      $sub_cat_id = get_subcat_category($conn, $subcategory_id);
      if ($sub_cat_id <= 0 || ($category_id > 0 && $category_id !== $sub_cat_id)) {
        $flash = 'selected subcategory does not match the chosen category.';
        $flash_type = 'error';
      } else {
        $price_val = floatval($price);

        if ($oldSub !== $subcategory_id) {
          // moving to a new subcategory: append to end there
          $stmt = $conn->prepare("SELECT COALESCE(MAX(sort_order),0)+1 AS next_order FROM item WHERE subcategory_id=?");
          $stmt->bind_param("i", $subcategory_id);
          $stmt->execute();
          $res = $stmt->get_result();
          $next = ($res && $res->num_rows) ? (int)$res->fetch_assoc()['next_order'] : 1;
          $stmt->close();

          $up = $conn->prepare("
            UPDATE item
            SET subcategory_id=?, name=?, description=?, price=?, sort_order=?
            WHERE id=?
          ");
          $up->bind_param("issdii", $subcategory_id, $name, $description, $price_val, $next, $id);
          $up->execute();
          $up->close();

          // normalize both old and new subcategories
          if ($oldSub > 0) normalize_item_order($conn, $oldSub);
          normalize_item_order($conn, $subcategory_id);
        } else {
          // same subcategory: keep current order
          $up = $conn->prepare("
            UPDATE item
            SET name=?, description=?, price=?
            WHERE id=?
          ");
          $up->bind_param("ssdi", $name, $description, $price_val, $id);
          $up->execute();
          $up->close();
        }

        $flash = 'item updated.';
        $flash_type = 'success';
      }
    }
  }

  // MOVE within same subcategory
  if ($action === 'move_up' || $action === 'move_down') {
    $id = intval($_POST['id'] ?? 0);
    if ($id > 0) {
      $st = $conn->prepare("SELECT id, subcategory_id, COALESCE(sort_order,0) AS sort_order FROM item WHERE id=?");
      $st->bind_param("i", $id);
      $st->execute();
      $cur = $st->get_result()->fetch_assoc();
      $st->close();

      if ($cur) {
        $subId    = (int)$cur['subcategory_id'];
        // normalize first to get contiguous order
        normalize_item_order($conn, $subId);

        // reload order value
        $st = $conn->prepare("SELECT COALESCE(sort_order,0) AS sort_order FROM item WHERE id=?");
        $st->bind_param("i", $id);
        $st->execute();
        $curOrder = (int)$st->get_result()->fetch_assoc()['sort_order'];
        $st->close();

        $targetOrder = ($action === 'move_up') ? $curOrder - 1 : $curOrder + 1;

        $q = $conn->prepare("SELECT id FROM item WHERE subcategory_id=? AND sort_order=? LIMIT 1");
        $q->bind_param("ii", $subId, $targetOrder);
        $q->execute();
        $nb = $q->get_result()->fetch_assoc();
        $q->close();

        if ($nb) {
          $nbId = (int)$nb['id'];
          $tmp  = -999999;

          $u = $conn->prepare("UPDATE item SET sort_order=? WHERE id=?");
          $u->bind_param("ii", $tmp, $id);
          $u->execute();

          $u->bind_param("ii", $curOrder, $nbId);
          $u->execute();

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
      $g = $conn->prepare("SELECT subcategory_id FROM item WHERE id=?");
      $g->bind_param("i", $id);
      $g->execute();
      $gr = $g->get_result()->fetch_assoc();
      $g->close();

      $stmt = $conn->prepare("DELETE FROM item WHERE id=?");
      $stmt->bind_param("i", $id);
      $stmt->execute();
      $stmt->close();

      if ($gr) normalize_item_order($conn, (int)$gr['subcategory_id']);

      $flash = 'item deleted.';
      $flash_type = 'success';
    }
  }
}

// ---------- LIST (grouped: category → subcategory → item order) ----------
$items = [];
$q = "
  SELECT i.id, i.name, i.description, i.price, COALESCE(i.sort_order,0) AS i_order,
         s.id AS sub_id, s.name AS sub_name, s.category_id, COALESCE(s.sort_order,0) AS s_order,
         c.id AS cat_id, c.name AS cat_name, COALESCE(c.sort_order,0) AS c_order
  FROM item i
  JOIN subcategory s ON s.id = i.subcategory_id
  JOIN category c ON c.id = s.category_id
  ORDER BY c_order ASC, c.id ASC, s_order ASC, s.id ASC, i_order ASC, i.id ASC
";
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
  :root { --bg:#0f1115; --card:#1a1d23; --text:#fff; --muted:#9aa0a6;
          --primary:#0d6efd; --primary-hover:#0b5ed7; --danger:#d93025;
          --border:#2b2f36; --radius:16px; --shadow:0 6px 20px rgba(0,0,0,.25); }
  *{ box-sizing:border-box }
  body{ margin:0; font-family:'Poppins',system-ui,-apple-system,Segoe UI,Roboto,Arial;
        background:var(--bg); color:var(--text); min-height:100vh; display:flex;
        align-items:flex-start; justify-content:center; padding:40px 20px; }
  .card{ background:var(--card); border:1px solid var(--border); border-radius:var(--radius);
         padding:25px 30px; box-shadow:var(--shadow); width:min(94vw, 1200px); }
  .toolbar{ display:flex; justify-content:space-between; align-items:center; }
  h1{ margin:6px 0 10px; text-align:center; }
  .back{ text-decoration:none; background:#6b7280; color:#fff; padding:10px 16px; border-radius:999px; font-size:14px; }
  .flash{ padding:10px; border-radius:10px; text-align:center; margin:15px 0; font-weight:500; }
  .flash.success{ background:#16a34a; } .flash.error{ background:#d93025; }

  .grid-form{ display:grid; grid-template-columns: 1.1fr 1.1fr 1.2fr 2fr .9fr auto;
              gap:12px; margin-bottom:22px; align-items:end; }
  @media (max-width: 1100px){ .grid-form{ grid-template-columns: 1fr 1fr; } }
  @media (max-width: 640px){ .grid-form{ grid-template-columns: 1fr; } }
  label{ display:block; font-size:12px; color:var(--muted); margin-bottom:6px; }
  input[type="text"], input[type="number"], select, textarea{
    width:100%; padding:12px; border-radius:10px; border:1px solid #444;
    background:#1e2229; color:#fff; outline:none;
  }
  textarea{ resize:vertical; min-height:42px; }
  select option{ color:#fff; background:#1a1d23; }
  .btn{ border:none; border-radius:999px; padding:12px 20px; font-weight:600; cursor:pointer; transition:.2s; color:#fff; }
  .btn.primary{ background:var(--primary); } .btn.primary:hover{ background:var(--primary-hover); }
  .btn.danger{ background:var(--danger); } .btn.secondary{ background:#6b7280; }

  .table{ width:100%; border-collapse:collapse; margin-top:6px; table-layout: fixed; }
  .table th, .table td{ border-bottom:1px solid #333; padding:12px 8px; text-align:left; vertical-align:middle; }
  .table th{ color:var(--muted); font-weight:600; font-size:14px; }

  .row-input, .row-select, .row-textarea, .row-price{
    width:100%; padding:10px; border:1px solid #444; border-radius:10px; background:#1e2229; color:#fff;
  }
  .row-textarea{ min-height:38px; resize:vertical; }
  .row-actions{ display:flex; gap:8px; flex-wrap:wrap; justify-content:flex-end; }

  .arrows{ display:flex; gap:6px; align-items:center; justify-content:flex-start; }
  .arrow-btn{ background:#2c313a; border:1px solid #3a3f48; color:#fff; width:38px; height:38px; border-radius:10px; cursor:pointer;
              display:flex; align-items:center; justify-content:center; transition:transform .08s, background .15s; }
  .arrow-btn:hover{ background:#3a3f48; } .arrow-btn:active{ transform:translateY(1px); }
  .arrow-btn small{ font-size:16px; line-height:1; }

  @media (max-width: 900px){
    .table{ border:0; } .table thead{ display:none; }
    .table tbody tr{ display:block; background:#1a1d23; border:1px solid var(--border); border-radius:12px; padding:12px; margin-bottom:12px; }
    .table td{ display:block; border:0; padding:8px 0; }
    .table td[data-label]::before{ content: attr(data-label); display:block; color: var(--muted); font-size:12px; margin-bottom:6px; }
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

    <!-- List / edit / move / delete -->
    <table class="table">
      <thead>
        <tr>
          <th style="width:70px;">ID</th>
          <th>Category</th>
          <th>Subcategory</th>
          <th>Item</th>
          <th>Description</th>
          <th style="width:110px;">Price</th>
          <th style="width:150px;">Order</th>
          <th style="width:220px;">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($items as $it): ?>
          <form id="f<?= (int)$it['id'] ?>" method="post"></form>
          <tr data-id="<?= (int)$it['id'] ?>">
            <td data-label="ID"><?= (int)$it['id'] ?></td>

            <td data-label="Category">
              <input type="hidden" name="id" value="<?= (int)$it['id'] ?>" form="f<?= (int)$it['id'] ?>">
              <select class="row-select js-cat" form="f<?= (int)$it['id'] ?>">
                <?php foreach ($cats as $c): ?>
                  <option value="<?= (int)$c['id'] ?>" <?= $c['id']==$it['cat_id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($c['name']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </td>

            <td data-label="Subcategory">
              <select class="row-select js-sub" name="subcategory_id" required form="f<?= (int)$it['id'] ?>">
                <?php foreach ($subs as $s): ?>
                  <option value="<?= (int)$s['id'] ?>"
                          data-cat="<?= (int)$s['category_id'] ?>"
                          <?= $s['id']==$it['sub_id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($s['name']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </td>

            <td data-label="Item">
              <input class="row-input" name="name" type="text" value="<?= htmlspecialchars($it['name']) ?>" required form="f<?= (int)$it['id'] ?>">
            </td>

            <td data-label="Description">
              <textarea class="row-textarea" name="description" placeholder="description…" form="f<?= (int)$it['id'] ?>"><?= htmlspecialchars($it['description']) ?></textarea>
            </td>

            <td data-label="Price">
              <input class="row-price" name="price" type="number" step="0.01" min="0" value="<?= number_format((float)$it['price'], 2, '.', '') ?>" required form="f<?= (int)$it['id'] ?>">
            </td>

            <td data-label="Order">
              <div class="arrows">
                <button type="submit" class="arrow-btn" title="Move up"   name="action" value="move_up"   form="f<?= (int)$it['id'] ?>"><small>▲</small></button>
                <button type="submit" class="arrow-btn" title="Move down" name="action" value="move_down" form="f<?= (int)$it['id'] ?>"><small>▼</small></button>
              </div>
            </td>

            <td data-label="Actions">
              <div class="row-actions">
                <button class="btn primary" type="submit" name="action" value="update" form="f<?= (int)$it['id'] ?>">Save</button>
                <button class="btn danger" type="submit" name="action" value="delete" form="f<?= (int)$it['id'] ?>"
                        onclick="return confirm('Delete this item?');">Delete</button>
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

  options.forEach(opt => {
    const d = parseInt(opt.getAttribute('data-cat') || '0', 10);
    if (!opt.getAttribute('data-cat')) { opt.hidden = false; return; } // placeholder
    opt.hidden = !(d === catId);
  });

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
document.querySelectorAll('form[id^="f"]').forEach(form => {
  const catSel = form.closest('tr').querySelector('.js-cat');
  const subSel = form.closest('tr').querySelector('.js-sub');
  if (catSel && subSel) {
    filterSubcats(catSel, subSel, true);
    catSel.addEventListener('change', () => filterSubcats(catSel, subSel, false));
    // ensure category_id is submitted too
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
