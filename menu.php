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

// All categories for the overlay (ordered like admin)
$allCats = [];
$cc = $conn->query("SELECT name, slug FROM category ORDER BY COALESCE(sort_order,0) ASC, id ASC");
while ($row = $cc->fetch_assoc()) $allCats[] = $row;
$cc && $cc->close();

// Subcategories (ordered within this category)
$subs = [];
$q = $conn->prepare("
  SELECT id, name
  FROM subcategory
  WHERE category_id = ?
  ORDER BY sort_order ASC, id ASC
");
$q->bind_param("i", $cat['id']);
$q->execute();
$r = $q->get_result();
while ($row = $r->fetch_assoc()) $subs[] = $row;
$q->close();

// Items: ordered by category's subcategory order, then item order
$items = [];
$iq = $conn->prepare("
  SELECT
    i.id,
    i.name,
    COALESCE(i.description, '') AS description,
    i.price,
    i.subcategory_id,
    COALESCE(s.sort_order, 0) AS s_order,
    COALESCE(i.sort_order, 0) AS i_order
  FROM item i
  JOIN subcategory s ON s.id = i.subcategory_id
  WHERE s.category_id = ?
  ORDER BY
    s_order ASC, s.id ASC,
    i_order ASC, i.id ASC
");
$iq->bind_param("i", $cat['id']);
$iq->execute();
$ri = $iq->get_result();
while ($row = $ri->fetch_assoc()) $items[] = $row;
$iq->close();

// Title: only Food has "MENU"
if (strtolower($cat['slug']) === 'food') {
  $title = strtoupper(trim($cat['name']) . ' MENU');
} else {
  $title = strtoupper(trim($cat['name']));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title><?= htmlspecialchars($title) ?></title>

<style>
/* ===== Fonts (iOS friendly) ===== */
@font-face{
  font-family:"Montserrat";
  src: url("fonts/Montserrat-Regular.woff2") format("woff2"),
       url("fonts/Montserrat-Regular.woff")  format("woff"),
       url("fonts/Montserrat-Regular.ttf")   format("truetype");
  font-weight:400; font-style:normal; font-display:swap;
}
@font-face{
  font-family:"Montserrat";
  src: url("fonts/Montserrat-Medium.woff2") format("woff2"),
       url("fonts/Montserrat-Medium.woff")  format("woff"),
       url("fonts/Montserrat-Medium.ttf")   format("truetype");
  font-weight:500; font-style:normal; font-display:swap;   /* << use for CONTACT US */
}
@font-face{
  font-family:"Montserrat";
  src: url("fonts/Montserrat-SemiBold.woff2") format("woff2"),
       url("fonts/Montserrat-SemiBold.woff")  format("woff"),
       url("fonts/Montserrat-SemiBold.ttf")   format("truetype");
  font-weight:600; font-style:normal; font-display:swap;
}
@font-face{
  font-family:"Montserrat";
  src: url("fonts/Montserrat-Bold.woff2") format("woff2"),
       url("fonts/Montserrat-Bold.woff")  format("woff"),
       url("fonts/Montserrat-Bold.ttf")   format("truetype");
  font-weight:700; font-style:normal; font-display:swap;
}
@font-face{
  font-family:"Montserrat";
  src: url("fonts/Montserrat-LightItalic.woff2") format("woff2"),
       url("fonts/Montserrat-LightItalic.woff")  format("woff"),
       url("fonts/Montserrat-LightItalic.ttf")   format("truetype");
  font-weight:300; font-style:italic; font-display:swap;
}

:root{
  --text:#9E3722;
  --accent:#9E3722;
  --underline:#9E3722;
  --overlay:#9E3722;
}

*{ box-sizing:border-box; }
html,body{ margin:0; height:100%; }

body{
  font-family:"Montserrat", system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif;
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
  font-family:"Montserrat", sans-serif;
  font-weight:700;
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
  font-family:"Montserrat", sans-serif;
  font-weight:300;
  font-size: 17px;
  text-transform: uppercase; letter-spacing:.06em;
  color: var(--text); text-decoration:none; position:relative;
  padding-bottom: 8px;
  transition: color .2s ease;
}
.tab.active{ font-weight:600; }
.tab.active::after{
  content:""; position:absolute; left:0; right:0; bottom:0;
  height: 1.5px;
  background: var(--underline);
  border-radius: 999px;
}

/* Items */
.items{ margin-top: 24px; display:grid; gap: 10px; }

.item{
  padding-bottom: 10px;
  display:grid;
  grid-template-columns: 1fr auto;
  align-items:start;
  column-gap: 12px;
  row-gap: 2px;
}
.item-name{
  font-family:"Montserrat", sans-serif;
  font-weight:600;
  text-transform: uppercase;
  font-size: 12px;
  line-height:1.15;
  color: var(--text);
}
.item-price{
  font-family:"Montserrat", sans-serif;
  font-weight:400;
  color: var(--accent);
  font-size: 12px;
  white-space: nowrap;
}
.item-desc{
  grid-column: 1 / -1;
  font-family:"Montserrat", sans-serif;
  font-weight:300; font-style:italic;
  font-size: 10px;
  color: #53504F;
  max-width: 35ch;
  overflow-wrap: anywhere;
  line-height: 1.25;
}

/* Header row + hamburger */
.header-row{ display:flex; justify-content:space-between; align-items:center; }
.menu-btn{ width: 28px; height: 18px; position:relative; cursor:pointer; }
.menu-btn span{
  position:absolute; left:0; right:0;
  height: 1.5px;
  background: var(--accent);
  border-radius: 999px;
  transform: scaleY(0.75);
  transform-origin: center;
}
.menu-btn span:nth-child(1){ top:0; }
.menu-btn span:nth-child(2){ top:8px; }
.menu-btn span:nth-child(3){ bottom:0; }

@media (min-width: 900px){
  .container{ padding: 36px 24px 56px; }
}
.menu-btn{ transform: translateY(-6px); }

/* ===== Category Overlay ===== */
#cat-overlay{
  position: fixed; inset: 0;
  background: var(--overlay);
  transform: translateX(100%);
  transition: transform .45s cubic-bezier(.2,.7,.2,1);
  visibility: hidden;
  pointer-events: none;
  z-index: 9999;
}
#cat-overlay.open{
  transform: translateX(0);
  visibility: visible;
  pointer-events: auto;
}

/* Close button (top-right) */
#cat-overlay .overlay-close{
  position: absolute; top: 18px; right: 18px;
  background: transparent; border: 0; color: #fff;
  font-size: 36px; line-height: 1; cursor: pointer;
}

/* Home icon (top-left) — aligned with X */
#cat-overlay .home-link{
  position: absolute; top: 18px; left: 18px;              /* same offsets as X */
  display: inline-flex; align-items:center; justify-content:center;
  width: 36px; height: 36px;                               /* match X size */
}
#cat-overlay .home-link img{
  width: 36px; height: 36px; display:block;
}

/* Centered category list */
#cat-overlay .cat-center{
  height: 100%;
  display: flex; flex-direction: column;
  align-items: center; justify-content: center;
  gap: clamp(12px, 3vh, 26px);
  padding: 24px;
  text-align: center;
}

/* Overlay links */
.cat-link{
  font-family:"Montserrat", sans-serif;
  font-weight:700;
  text-transform: uppercase;
  letter-spacing: .06em; 
  color: #fff;
  text-decoration: none;
  font-size: clamp(22px, 3.6vw, 44px);
  opacity: 0;
  transform: translateX(30px);
  transition: transform .45s ease, opacity .45s ease;
}
#cat-overlay.open .cat-link{ opacity: 1; transform: translateX(0); }
#cat-overlay.open .cat-link.active{ opacity: .35; }
.cat-link:hover{ transform: translateX(0) translateY(-1px); }
.cat-link:focus, .cat-link:focus-visible{ outline: none; box-shadow: none; text-decoration: none; }

/* CONTACT section + icons */
.contact-wrap{
  position: absolute;
  left: 0; right: 0; bottom: 52px;
  text-align: center;
}
.contact-title{
  color:#F3EBDF;
  font-family:"Montserrat",sans-serif;
  font-weight:500;                      /* Montserrat-Medium */
  letter-spacing:.06em;
  text-transform:uppercase;
  font-size: 18px;
  margin-bottom: 12px;
}
.icon-row{
  display:flex; gap: 9px;
  justify-content:center; align-items:center;
}
.icon-link{
  display:inline-flex; align-items:center; justify-content:center;
  width:auto; height:auto;              /* no circle background */
}
.icon-link img{
  width: 34px; height: 34px; display:block;  /* bigger pure SVG */
}
@media (min-width:480px){
  .icon-link img{ width: 38px; height: 38px; }
}

/* Stagger (first 8 entries) */
#cat-overlay.open .cat-link:nth-child(1){ transition-delay: .06s; }
#cat-overlay.open .cat-link:nth-child(2){ transition-delay: .11s; }
#cat-overlay.open .cat-link:nth-child(3){ transition-delay: .16s; }
#cat-overlay.open .cat-link:nth-child(4){ transition-delay: .21s; }
#cat-overlay.open .cat-link:nth-child(5){ transition-delay: .26s; }
#cat-overlay.open .cat-link:nth-child(6){ transition-delay: .31s; }
#cat-overlay.open .cat-link:nth-child(7){ transition-delay: .36s; }
#cat-overlay.open .cat-link:nth-child(8){ transition-delay: .41s; }
</style>
</head>
<body>

  <div class="container">
    <div class="panel">
      <div class="header-row">
        <h1><?= htmlspecialchars($title) ?></h1>
        <div class="menu-btn" id="hamburger" aria-label="menu" aria-controls="cat-overlay" aria-expanded="false">
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

  <!-- Full-screen Category Overlay -->
  <div id="cat-overlay" aria-hidden="true">
    <!-- Home icon (top-left) -->
    <a class="home-link" href="index.php" aria-label="Home">
      <img src="images/icons/home_icon.svg" alt="">
    </a>

    <button class="overlay-close" aria-label="Close">×</button>

    <div class="cat-center">
      <?php foreach ($allCats as $c):
        $isActive = (strtolower($c['slug']) === $slug);
      ?>
        <a
          class="cat-link<?= $isActive ? ' active' : '' ?>"
          href="menu.php?category=<?= htmlspecialchars(strtolower($c['slug'])) ?>">
          <?= htmlspecialchars($c['name']) ?>
        </a>
      <?php endforeach; ?>
    </div>

    <!-- CONTACT + icons at bottom -->
    <div class="contact-wrap">
      <div class="contact-title">CONTACT US</div>
      <div class="icon-row">
        <a class="icon-link" href="tel:+96170202448" aria-label="Call">
          <img src="images/icons/phone_icon.svg" alt="">
        </a>
        <a class="icon-link" href="https://www.instagram.com/oops.restocafe?igsh=MTd0NzA3d2szZmQ4Mw==" target="_blank" rel="noopener" aria-label="Instagram">
          <img src="images/icons/insta_icon.svg" alt="">
        </a>
      </div>
    </div>
  </div>

<script>
// Subcategory tab switching
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

// Overlay open/close with slide-in animation
(function(){
  const btn   = document.getElementById('hamburger');
  const layer = document.getElementById('cat-overlay');
  const close = layer?.querySelector('.overlay-close');

  if (!btn || !layer) return;

  const open = () => {
    layer.classList.add('open');
    layer.setAttribute('aria-hidden', 'false');
    btn.setAttribute('aria-expanded', 'true');
    const firstLink = layer.querySelector('.cat-link');
    if (firstLink) firstLink.focus();
  };
  const hide = () => {
    layer.classList.remove('open');
    layer.setAttribute('aria-hidden', 'true');
    btn.setAttribute('aria-expanded', 'false');
    btn.focus();
  };

  btn.addEventListener('click', open);
  close?.addEventListener('click', hide);
  layer.addEventListener('click', (e) => { if (e.target === layer) hide(); });
  document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && layer.classList.contains('open')) hide(); });
})();
</script>

</body>
</html>
