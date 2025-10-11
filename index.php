<?php
// index.php
include 'db.php';

// Load categories from DB
$cats = [];
$res = $conn->query("SELECT id, name FROM category ORDER BY id ASC");
while ($r = $res->fetch_assoc()) {
  $cats[] = $r;
}
$res && $res->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>oOps! Menu</title>

<style>
  /* Load local Montserrat Light font */
  @font-face {
    font-family: "Montserrat";
    src: url("fonts/Montserrat-Light.ttf") format("truetype");
    font-weight: 300;
    font-style: normal;
    font-display: swap;
  }

  :root {
    --text: #ffffff;
  }

  html, body {
    height: 100%;
    margin: 0;
    scroll-behavior: smooth;
  }

  body {
    font-family: "Montserrat", sans-serif;
    color: var(--text);
    /* Background starts from the top, scrolls normally, fills entire screen */
    background: url("images/background/landing_background\ -\ Copy.png") top center / cover no-repeat;
    background-attachment: scroll;
    overflow-x: hidden;
    background-color: #9E3722;
  }

  /* Each section covers full screen height */
  .section {
    min-height: 100vh;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    padding: 24px;
  }

  /* MENU button (text only) */
  .menu-link {
    text-decoration: none;
    color: var(--text);
    font-weight: 300;
    letter-spacing: 0.16em;
    font-size: clamp(22px, 6vw, 34px);
    background: none;
    border: none;
    outline: none;
    cursor: pointer;
    transition: opacity 0.2s ease;
  }

  .menu-link:hover {
    opacity: 0.85;
  }

  /* Category list section */
  .list {
    width: 100%;
    max-width: 520px;
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    gap: clamp(14px, 3.5vh, 20px);
  }

  .cat-link {
    text-decoration: none;
    color: var(--text);
    text-transform: uppercase;
    font-weight: 700;
    letter-spacing: 0.12em;
    font-size: clamp(18px, 4.8vw, 28px);
    line-height: 1.2;
    background: none;
    padding: 4px 0;
    transition: opacity 0.2s ease;
  }

  .cat-link:hover {
    opacity: 0.9;
  }
</style>
</head>
<body>

  <!-- Top section (MENU text) -->
  <section class="section" id="top">
    <a href="#menu" class="menu-link">MENU</a>
  </section>

  <!-- Bottom section (Category links) -->
  <section class="section" id="menu">
    <div class="list">
      <?php foreach ($cats as $c): ?>
        <a class="cat-link" href="menu.php?category_id=<?= (int)$c['id'] ?>">
          <?= htmlspecialchars($c['name']) ?>
        </a>
      <?php endforeach; ?>
    </div>
  </section>

</body>
</html>
