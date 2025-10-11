<?php
// menu.php
include 'db.php';

// Get category by slug (?category=food)
$slug = strtolower(trim($_GET['category'] ?? ''));
if ($slug === '') { header("Location: index.php#menu"); exit(); }

$stmt = $conn->prepare("SELECT id, name, slug FROM category WHERE slug = ? LIMIT 1");
$stmt->bind_param("s", $slug);
$stmt->execute();
$res = $stmt->get_result();
$cat = $res->fetch_assoc();
$stmt->close();

if (!$cat) { header("Location: index.php#menu"); exit(); }

// Subcategories
$subs = [];
$q = $conn->prepare("SELECT id, name FROM subcategory WHERE category_id=? ORDER BY name ASC");
$q->bind_param("i", $cat['id']);
$q->execute();
$r = $q->get_result();
while ($row = $r->fetch_assoc()) $subs[] = $row;
$q->close();

// Items (defensive de-dup)
$items = [];
$iq = $conn->prepare("
  SELECT MIN(i.id) AS id, i.name, i.description, i.price, i.subcategory_id
  FROM item i
  JOIN subcategory s ON s.id = i.subcategory_id
  WHERE s.category_id = ?
  GROUP BY i.name, i.description, i.price, i.subcategory_id
  ORDER BY i.name ASC
");
$iq->bind_param("i", $cat['id']);
$iq->execute();
$ri = $iq->get_result();
while ($row = $ri->fetch_assoc()) $items[] = $row;
$iq->close();

// Title: ensure it reads "... MENU"
$title = strtoupper(trim($cat['name']));
if (stripos($title, 'MENU') === false) { $title .= ' MENU'; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title><?= htmlspecialchars($title) ?></title>

<style>
/* Fonts */
@font-face { font-family:"Montserrat-Bold"; src:url("fonts/Montserrat-Bold.ttf") format("truetype"); font-weight:700; font-style:normal; font-display:swap; }
@font-face { font-family:"Montserrat-SemiBold"; src:url("fonts/Montserrat-SemiBold.ttf") format("truetype"); font-weight:600; font-style:normal; font-display:swap; }
@font-face { font-family:"Montserrat-Regular"; src:url("fonts/Montserrat-Regular.ttf") format("truetype"); font-weight:400; font-style:normal; font-display:swap; }
@font-face { font-family:"Montserrat-Light"; src:url("fonts/Montserrat-Light.ttf") format("truetype"); font-weight:300; font-style:normal; font-display:swap; }
@font-face { font-family:"Montserrat-LightItalic"; src:url("fonts/Montserrat-LightItalic.ttf") format("truetype"); font-weight:300; font-style:italic; font-display:swap; }

:root{
  /* All text colors (except descriptions) */
  --text:#9E3722;
  --accent:#9E3722;
  --underline:#9E3722;
}

*{ box-sizing:border-box; }
html,body{ margin:0; height:100%; }

body{
  font-family:"Montserrat-Regular", system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif;
  color:var(--text);
  background: url("images/background/home-background.png") top center / cover no-repeat;
  background-attachment: scroll;
}

/* Layout */
.container{ max-width: 820px; margin: 0 auto; padding: 24px 18px 36px; }
.panel{ background: transparent; border-radius: 0; padding: clamp(18px, 3vw, 26px) clamp(16px, 3vw, 28px); }

/* Header */
h1{
  margin: 0 0 14px;
  font-family:"Montserrat-Bold", sans-serif;
  font-size: 31px;
  letter-spacing: .04em;
  text-transform: uppercase;
  color: var(--accent);
}

/* Tabs */
.tabs{
  display:flex; gap: clamp(20px, 5vw, 40px);
  overflow-x:auto; -webkit-overflow-scrolling:touch; scrollbar-width:none;
  margin: 8px 0 8px; padding: 8px 2px 12px; position:relative;
}
.tabs::-webkit-scrollbar{ display:none; }

.tab{
  flex:0 0 auto;
  font-family:"Montserrat-Light", sans-serif;  /* default */
  font-size: 17px;
  text-transform: uppercase; letter-spacing:.06em;
  color: var(--text); text-decoration:none; position:relative;
  padding-bottom: 8px; /* space for underline */
  transition: color .2s ease;
}
.tab.active{ font-family:"Montserrat-SemiBold", sans-serif; }
.tab.active::after{
  content:""; position:absolute; left:0; right:0; bottom:0;
  height: 2px;                      /* thinner */
  background: var(--underline);
  border-radius: 999px;              /* fully rounded ends */
}

/* Items */
.items{ margin-top: 24px; display:grid; gap: 10px; } /* tighter list spacing */

.item{
    padding-bottom: 10px;
  display:grid;
  grid-template-columns: 1fr auto;
  align-items:start;
  column-gap: 12px;
  row-gap: 2px;                     /* tighter name→desc spacing */
}

.item-name{
  font-family:"Montserrat-SemiBold", sans-serif;
  text-transform: uppercase;
  font-size: 11px;
  line-height:1.15;
  color: var(--text);                /* ensure name uses #9E3722 */
}

.item-price{
  font-family:"Montserrat-Regular", sans-serif;
  color: var(--accent);              /* #9E3722 */
  font-size: 11px;
  white-space: nowrap;
}

/* Description: softer color, limited width, wraps nicely */
.item-desc{
  grid-column: 1 / -1;
  font-family:"Montserrat-LightItalic", sans-serif;
  font-size: 8px;
  color: rgba(0,0,0,.55);            /* keep softer than main text */
  max-width: 55ch;                   /* limit horizontal width */
  overflow-wrap: anywhere;           /* wrap long words if needed */
  line-height: 1.25;
}

/* Header row + hamburger (thin like underline) */
.header-row{ display:flex; justify-content:space-between; align-items:center; }
.menu-btn{ width: 28px; height: 18px; position:relative; cursor:pointer; }
.menu-btn span{
  position:absolute; left:0; right:0;
  height: 2px;                       /* thinner to match underline */
  background: var(--accent);
  border-radius: 999px;
}
.menu-btn span:nth-child(1){ top:0; }
.menu-btn span:nth-child(2){ top:8px; }
.menu-btn span:nth-child(3){ bottom:0; }

@media (min-width: 900px){
  .container{ padding: 36px 24px 56px; }
}
</style>
</head>
<body>

  <div class="container">
    <div class="panel">
      <div class="header-row">
        <h1><?= htmlspecialchars($title) ?></h1>
        <div class="menu-btn" aria-label="menu">
          <span></span><span></span><span></span>
        </div>
      </div>

      <!-- Subcategory tabs -->
      <nav class="tabs" id="tabs">
        <?php $firstSubId = $subs[0]['id'] ?? 0; ?>
        <?php foreach ($subs as $s): ?>
          <a href="#"
             class="tab<?= ($s['id']===$firstSubId?' active':'') ?>"
             data-sub="<?= (int)$s['id'] ?>">
             <?= htmlspecialchars($s['name']) ?>
          </a>
        <?php endforeach; ?>
      </nav>

      <!-- Items -->
      <section class="items" id="items">
        <?php foreach ($items as $it): ?>
          <article class="item" data-sub="<?= (int)$it['subcategory_id'] ?>">
            <div class="item-name"><?= htmlspecialchars($it['name']) ?></div>
            <div class="item-price"><?= rtrim(rtrim(number_format((float)$it['price'], 2, '.', ''), '0'), '.') ?>$</div>
            <?php if (trim($it['description']) !== ''): ?>
              <div class="item-desc"><?= htmlspecialchars($it['description']) ?></div>
            <?php endif; ?>
          </article>
        <?php endforeach; ?>
      </section>
    </div>
  </div>

<script>
// Filter items by subcategory and set active tab underline
(function(){
  const tabs = document.querySelectorAll('.tab');
  const items = document.querySelectorAll('#items .item');

  function setActive(subId){
    tabs.forEach(t => t.classList.toggle('active', t.dataset.sub === subId));
    items.forEach(it => { it.style.display = (it.dataset.sub === subId) ? 'grid' : 'none'; });
  }

  tabs.forEach(t => t.addEventListener('click', (e) => { e.preventDefault(); setActive(t.dataset.sub); }));

  const first = document.querySelector('.tab');
  if (first) setActive(first.dataset.sub);
})();
</script>

</body>
</html>
