<?php
include 'db.php';

// helper to build a URL-safe slug
function make_slug($s) {
  $s = iconv('UTF-8','ASCII//TRANSLIT',$s);
  $s = strtolower(trim($s));
  $s = preg_replace('/[^a-z0-9]+/','-',$s);
  return trim($s, '-');
}

// Load categories ordered by sort_order then id, ensure slug exists
$cats = [];
$sql  = "SELECT id, name, COALESCE(slug, '') AS slug, COALESCE(sort_order, 0) AS sort_order
         FROM category
         ORDER BY sort_order ASC, id ASC";
$res = $conn->query($sql);

while ($row = $res->fetch_assoc()) {
  if ($row['slug'] === '') {
    $slug = make_slug($row['name']);
    $upd = $conn->prepare("UPDATE category SET slug=? WHERE id=?");
    $upd->bind_param("si", $slug, $row['id']);
    $upd->execute();
    $upd->close();
    $row['slug'] = $slug;
  }
  $cats[] = $row;
}
$res && $res->close();

// ----- SEO helpers -----
$scheme   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host     = $_SERVER['HTTP_HOST'] ?? 'oopsrestocafe.com';
$homeUrl  = $scheme . '://' . $host . '/';
$siteName = 'oOps Resto Cafe';  // <- adjust to your brand styling
$pageTitle = $siteName . ' — Menu & Categories';
$description = 'Explore the oOps Resto Cafe menu: appetizers, salads, sandwiches, burgers, pasta, and more. Fresh ingredients, bold flavors, and a cozy vibe.';
$ogImage = $homeUrl . 'images/og/og-image.jpg'; // <- put a 1200x630 image here
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />

<title><?= htmlspecialchars($pageTitle) ?></title>
<meta name="description" content="<?= htmlspecialchars($description) ?>" />
<link rel="canonical" href="<?= htmlspecialchars($homeUrl) ?>" />

<!-- Open Graph -->
<meta property="og:type" content="website" />
<meta property="og:title" content="<?= htmlspecialchars($pageTitle) ?>" />
<meta property="og:description" content="<?= htmlspecialchars($description) ?>" />
<meta property="og:url" content="<?= htmlspecialchars($homeUrl) ?>" />
<meta property="og:site_name" content="<?= htmlspecialchars($siteName) ?>" />
<meta property="og:image" content="<?= htmlspecialchars($ogImage) ?>" />

<!-- Twitter Card -->
<meta name="twitter:card" content="summary_large_image" />
<meta name="twitter:title" content="<?= htmlspecialchars($pageTitle) ?>" />
<meta name="twitter:description" content="<?= htmlspecialchars($description) ?>" />
<meta name="twitter:image" content="<?= htmlspecialchars($ogImage) ?>" />

<!-- Favicons (optional: drop files in /images/favicons/) -->
<link rel="icon" href="/images/favicons/favicon.ico">
<link rel="apple-touch-icon" href="/images/favicons/apple-touch-icon.png" sizes="180x180">
<link rel="icon" type="image/png" href="/images/favicons/favicon-32x32.png" sizes="32x32">
<link rel="icon" type="image/png" href="/images/favicons/favicon-16x16.png" sizes="16x16">

<!-- Fonts (keep TTF for now; add WOFF2 later if you export them) -->
<style>
  @font-face {
    font-family: "Montserrat-Light";
    src: url("fonts/Montserrat-Light.ttf") format("truetype");
    font-weight: 300;
    font-style: normal;
    font-display: swap;
  }
  @font-face {
    font-family: "Montserrat-Bold";
    src: url("fonts/Montserrat-Bold.ttf") format("truetype");
    font-weight: 700;
    font-style: normal;
    font-display: swap;
  }

  :root { --text:#ffffff; }

  /* Accessible, visually-hidden H1 (SEO) */
  .sr-only{
    position:absolute !important;
    width:1px; height:1px;
    padding:0; margin:-1px; overflow:hidden;
    clip:rect(0,0,0,0); white-space:nowrap; border:0;
  }

  html, body{ height:100%; margin:0; scroll-behavior:smooth; }

  body{
    font-family:"Montserrat-Light", system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif;
    color:var(--text);
    background: url("images/background/landing-background.png") top center no-repeat;
    background-size: 100% 200vh;  /* covers both sections */
    background-attachment: scroll;
    overflow-x:hidden;
    background-color:#9E3722;
  }

  .list{
    width:100%; max-width:520px;
    display:flex; flex-direction:column; align-items:center; text-align:center;
    gap:clamp(14px, 3.5vh, 20px);
    padding-bottom:180px;
  }

  .section{
    min-height:100vh;
    display:flex; flex-direction:column; justify-content:center; align-items:center;
    padding:0;
  }

  .menu-link{
    text-decoration:none; color:#F3EBDF;
    font-weight:300; letter-spacing:.16em;
    font-size:clamp(46px, 6vw, 34px);
    background:none; border:0; outline:0; cursor:pointer;
    padding-top:178px; transition:opacity .2s ease;
  }
  .menu-link:hover{ opacity:.85; }

  .cat-link{
    font-family:"Montserrat-Bold", sans-serif;
    text-decoration:none; color:#F3EBDF; text-transform:uppercase; font-weight:700;
    letter-spacing:0; font-size:clamp(30px, 4.8vw, 28px); line-height:1.2;
    background:none; padding:4px 0; transition:opacity .2s ease;
  }
  .cat-link:hover{ opacity:.9; }
</style>

<!-- JSON-LD: basic Restaurant schema -->
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "Restaurant",
  "name": "<?= htmlspecialchars($siteName) ?>",
  "url": "<?= htmlspecialchars($homeUrl) ?>",
  "description": "<?= htmlspecialchars($description) ?>",
  "image": "<?= htmlspecialchars($ogImage) ?>"
}
</script>
</head>
<body>

<!-- Hidden H1 purely for SEO/Accessibility -->
<h1 class="sr-only"><?= htmlspecialchars($siteName) ?> — Menu</h1>

<!-- Top section -->
<section class="section" id="top">
  <a href="#menu" class="menu-link">MENU</a>
</section>

<!-- Bottom section: categories -->
<section class="section" id="menu">
  <div class="list">
    <?php foreach ($cats as $c): ?>
      <a class="cat-link"
         href="menu.php?category=<?= urlencode($c['slug']) ?>"
         data-id="<?= (int)$c['id'] ?>">
        <?= htmlspecialchars($c['name']) ?>
      </a>
    <?php endforeach; ?>
  </div>
</section>

</body>
</html>
