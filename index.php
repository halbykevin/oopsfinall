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
    /* Load local Montserrat Light and Bold fonts */
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


    :root {
      --text: #ffffff;
    }

    html, body {
      height: 100%;
      margin: 0;
      scroll-behavior: smooth;
    }

    /* 1) Background: start at top, scroll, no repeat, fill TWO screens (200vh) */
  body {
    font-family: "Montserrat-Light", sans-serif;
    color: var(--text);
    background: url("images/background/backgroundtest1.png") top center no-repeat;
    background-size: 100% 200vh;   /* 👈 stretch image to cover both sections */
    background-attachment: scroll;
    overflow-x: hidden;
    background-color: #9E3722;
  }

  /* 2) Remove the artificial white space at the bottom */
    /* Category list section */
  .list {
    width: 100%;
    max-width: 520px;
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    gap: clamp(14px, 3.5vh, 20px);
    padding-bottom: 180px;             
  }


    /* Each section covers full screen height */
    .section {
      min-height: 100vh;
      display: flex;
      flex-direction: column;
      justify-content: center;
      align-items: center;
      padding: 0px;
    }

    /* MENU button (text only) */
    .menu-link {
      text-decoration: none;
      color: var(--text);
      font-weight: 300;
      letter-spacing: 0.16em;
      font-size: clamp(46px, 6vw, 34px);
      background: none;
      border: none;
      outline: none;
      cursor: pointer;
      padding-top: 178px;
      transition: opacity 0.2s ease;
    }

    .menu-link:hover {
      opacity: 0.85;
    }
    

    .cat-link {
      font-family: "Montserrat-Bold", sans-serif;
      text-decoration: none;
      color: var(--text);
      text-transform: uppercase;
      font-weight: 700;
      letter-spacing: 0em;
      font-size: clamp(36px, 4.8vw, 28px);
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
