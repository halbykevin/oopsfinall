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
  justify-content:center;
  min-height:100vh;
  position:relative;
  overflow:hidden;
}

/* Top emblem behind text */
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

/* Content */
.container{
  position:relative;
  z-index:1;
  text-align:center;
  display:flex;
  flex-direction:column;
  align-items:center;
}

/* Logo at bottom */
.logo{
  width:100px;
  height:auto;
  margin-top:30px;
}

/* Text */
h1{
  font-size:15px;
  font-weight:700;
  text-transform:uppercase;
  text-align:center;
  letter-spacing:.4px;
  color:var(--brand);
  margin-top:40px;
}
p{
  text-align:center;
  font-weight:300;
  font-size:13px;
  color:var(--brand);
  max-width:260px;
  line-height:1.6;
  margin:10px auto 18px;
}
.hr{
  width:80px;
  height:2px;
  background:rgba(158,55,34,.35);
  border-radius:2px;
  margin:12px auto 24px;
}
</style>
</head>
<body>

<div class="container">
  <h1>Thank you for your<br>Valuable Feedback</h1>
  <p>We’ll keep improving so<br>you’ll do it again.</p>

  <div class="hr"></div>

  <!-- oOps logo instead of text -->
  <img src="images/logo/oopslogo_web.svg" alt="oOps Logo" class="logo">
</div>

</body>
</html>
