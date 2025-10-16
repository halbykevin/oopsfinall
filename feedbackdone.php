<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width,initial-scale=1" />
<title>Thank You!</title>

<style>
@font-face{
  font-family:"Montserrat";
  src:url("fonts/Montserrat-Light.ttf") format("truetype");
  font-weight:300;
}
@font-face{
  font-family:"Montserrat";
  src:url("fonts/Montserrat-Bold.ttf") format("truetype");
  font-weight:700;
}

:root{
  --brand:#9E3722;
  --bg:#F3EBDF;
  --text:#9E3722;

  /* Emblem layout controls (px for device-consistent placement) */
  --emblem-top: 88px;     /* distance from very top of page */
  --emblem-size: 110px;   /* emblem width */
  --emblem-gap: 26px;     /* space between emblem and heading */
}

*{box-sizing:border-box;margin:0;padding:0}
html,body{height:100%}
body{
  background:var(--bg);
  color:var(--text);
  font-family:"Montserrat",sans-serif;
  display:flex;
  flex-direction:column;
  align-items:center;
  justify-content:flex-start;  /* start since we reserve space ourselves */
  min-height:100vh;
  position:relative;
  overflow:hidden;
}

/* (Optional) faint background emblem — disabled to avoid confusion with the top icon */
/*
body::before{
  content:"";
  position:absolute;
  top:100px;
  left:50%;
  transform:translateX(-50%);
  width:220px;
  height:220px;
  background:url('images/icons/emblem_form_p1.svg') no-repeat center/contain;
  opacity:.08;
  z-index:0;
}
*/

/* Foreground emblem: locked in place across devices */
.top-emblem{
  position:absolute;              /* anchor to the page, not the flow */
  top: var(--emblem-top);
  left:50%;
  transform:translateX(-50%);
  width: var(--emblem-size);
  height:auto;
  z-index: 2;
  pointer-events:none;            /* never block taps/clicks */
}

/* Content sits below emblem automatically */
.container{
  position:relative;
  z-index:1;
  text-align:center;
  display:flex;
  flex-direction:column;
  align-items:center;
  /* Reserve space = emblem top + emblem size + gap */
  padding-top: calc(var(--emblem-top) + var(--emblem-size) + var(--emblem-gap));
  width:min(92vw, 420px);
}

/* Logo at bottom */
.logo{
  width:120px;
  height:auto;
  margin-top:30px;
}

/* Text */
h1{
  font-size:17px;
  font-weight:700;
  text-transform:uppercase;
  text-align:center;
  letter-spacing:.4px;
  color:var(--brand);
}
p {
  text-align: center;
  font-weight: 300;
  font-size: 17px;
  color: var(--brand);
  max-width: 260px;
  line-height: 1.6;
  margin: 10px auto 18px;
  text-transform: uppercase;
}

.hr{
  width:150px;
  height:2px;
  background:rgba(158,55,34,.35);
  border-radius:2px;
  margin:12px auto 24px;
}


 
/* Small screens: scale emblem a bit but keep same visual position logic */
@media (max-width: 380px){
  :root{
    --emblem-top: 72px;
    --emblem-size: 96px;
    --emblem-gap: 22px;
  }
}
</style>
</head>
<body>

  <!-- Emblem at the top center (stays put across devices) -->
  <img src="images/icons/emblem_form_p2.svg" alt="Emblem" class="top-emblem"><br>

  <div class="container">
    <h1>Thank you for your<br>Valuable Feedback</h1>
    <p>We’ll keep improving so<br>you’ll do it again.</p>

    <div class="hr"></div>

    <!-- oOps logo instead of text -->
    <img src="images/logo/form_p2_logodark.svg" alt="oOps Logo" class="logo">
  </div>

</body>
</html>
