<?php
/**
 * NIXSHOOTS CMS - Main Entry Point
 * Version: 4.0.0
 * testing
 * Public-facing site with performance optimizations
 */

define('NIX_CMS', true);
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

// Initialize database
db_init();

// Get cached page or generate
$pageCache = cache_get('index_page');
if ($pageCache && !$isVercel) {
    echo $pageCache;
    exit;
}

// Fetch settings
$settings = [];
$keys = ['mark', 'handle', 'tagline', 'loc', 'email', 'wa', 'statement', 'about', 'avail', 'accent'];
foreach ($keys as $key) {
    $settings[$key] = get_setting($key, '');
}

// Set accent color
$accent = $settings['accent'] ?: '#ff3b30';

// Get collections
$collections = get_collections();

// Get pages
$aboutPage = get_page('about');
$footerPage = get_page('footer');

ob_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<title><?= esc($settings['mark'] ?: 'NIXSHOOTS') ?> — Photographs</title>
<link rel="icon" href="data:,">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Anton&family=Fraunces:ital,opsz,wght@1,9..144,400;1,9..144,600&family=Space+Grotesk:wght@400;500;700&display=swap" rel="stylesheet">
<meta name="theme-color" content="#0f0e0c">
<meta name="description" content="<?= esc($settings['tagline'] ?: 'Photography by NIXSHOOTS') ?>">
<style>
:root{--bg:#0f0e0c;--bg2:#161411;--tx:#f1ece2;--mut:#9a938a;--red:<?= $accent ?>;--yel:#ffcf24;--line:#2b2823}
*{margin:0;padding:0;box-sizing:border-box}
html{scroll-behavior:smooth}
body{background:var(--bg);color:var(--tx);font-family:'Space Grotesk',sans-serif;font-size:16px;line-height:1.6;overflow-x:hidden;-webkit-tap-highlight-color:transparent}
::selection{background:var(--red);color:#fff}
img{display:block;max-width:100%}
a{color:inherit;text-decoration:none}
button{font-family:inherit;cursor:pointer}
[hidden]{display:none!important}
.display{font-family:'Anton',sans-serif;text-transform:uppercase;line-height:.92;font-weight:400}
.mono{font-size:11px;letter-spacing:.14em;text-transform:uppercase;font-weight:500;color:var(--mut)}
.mono b{color:var(--red);font-weight:500}
.rd{color:var(--red)}
.wrap{max-width:1280px;margin:0 auto;padding:0 24px}
#noise{position:fixed;inset:0;z-index:4;pointer-events:none;opacity:.06;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='160' height='160'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='2'/%3E%3C/filter%3E%3Crect width='160' height='160' filter='url(%23n)' opacity='0.6'/%3E%3C/svg%3E")}
.ph{position:relative;background:var(--ph,#1b1712)}
.ph::before{content:attr(data-ph);position:absolute;inset:0;display:flex;align-items:center;justify-content:center;text-align:center;padding:8px;pointer-events:none;font-size:9px;letter-spacing:.16em;text-transform:uppercase;color:rgba(241,236,230,.55);animation:phblink 1.7s ease-in-out infinite}
@keyframes phblink{50%{opacity:.4}}
.ph .pimg{opacity:0;transition:opacity .6s ease}
.ph.ld .pimg{opacity:1}
.ph.ld::before{display:none}
.ph.err::before{content:"frame missing — nix ©";animation:none;color:rgba(255,59,48,.75)}
header{position:fixed;top:0;left:0;right:0;z-index:20;background:var(--bg);border-bottom:1px solid var(--line)}
.hbar{display:flex;align-items:center;justify-content:space-between;padding:13px 24px;max-width:1400px;margin:0 auto;gap:14px}
.wordmark{font-family:'Anton';font-size:22px;text-transform:uppercase;flex:0 0 auto}
.wordmark em{font-style:normal;color:var(--red)}
nav{display:flex;gap:20px;align-items:center}
nav a{font-size:11px;letter-spacing:.14em;text-transform:uppercase;color:var(--mut);transition:color .2s;white-space:nowrap}
nav a:hover{color:var(--red)}
.hbook{background:none;border:1px solid var(--red);color:var(--red);font-size:11px;letter-spacing:.16em;text-transform:uppercase;padding:8px 14px;transition:all .2s}
.hbook:hover{background:var(--red);color:#fff}
#top{padding:128px 0 40px;background-image:radial-gradient(rgba(255,255,255,.05) 1px,transparent 1.5px);background-size:26px 26px}
.hero-meta{display:flex;justify-content:space-between;margin-bottom:18px;gap:10px;flex-wrap:wrap}
.dot{display:inline-block;width:7px;height:7px;border-radius:50%;background:var(--red);margin-right:6px;animation:pulse 1.6s infinite}
@keyframes pulse{0%,100%{opacity:1}50%{opacity:.25}}
h1.big{font-family:'Anton';font-size:clamp(44px,14.5vw,196px);text-transform:uppercase;line-height:.88}
.hero-sub{display:grid;grid-template-columns:1fr 1.1fr;gap:40px;margin-top:34px;align-items:start}
.tag{font-family:'Fraunces';font-style:italic;font-size:clamp(22px,3vw,34px);line-height:1.25}
.hero-lines{margin-top:22px;display:grid;gap:6px}
.scrollcue{margin-top:26px;display:flex;justify-content:space-between;gap:10px}
.strip{border-top:1px solid var(--line);border-bottom:1px solid var(--line);overflow:hidden;background:#0a0908;padding:6px 0}
.holes{height:10px;background:repeating-linear-gradient(90deg,#221d17 0 16px,transparent 16px 34px)}
.strip-track{display:flex;gap:14px;width:max-content;animation:strip 46s linear infinite;padding:8px 0}
.strip:hover .strip-track{animation-play-state:paused}
.paused .strip-track{animation-play-state:paused}
@keyframes strip{to{transform:translateX(-50%)}}
.sframe{width:230px;background:#0a0908;padding:8px 8px 4px;border:1px solid #222}
.sfim{height:140px;overflow:hidden}
.sfim img{width:100%;height:100%;object-fit:cover}
.sframe span{display:block;padding:5px 2px 3px;color:var(--yel);font-size:10px;letter-spacing:.12em;text-transform:uppercase}
#index,#series,#statement,#about,#contact{content-visibility:auto;contain-intrinsic-size:auto 900px}
.shead{display:flex;align-items:baseline;gap:16px;border-top:1px solid var(--line);padding:20px 0 34px;margin-top:70px;flex-wrap:wrap}
.shead .no{color:var(--red)}
.shead h2{font-family:'Anton';font-size:clamp(34px,5vw,64px);text-transform:uppercase}
.shead .lbl{margin-left:auto}
#idx{border-top:1px solid var(--line)}
.irow{display:grid;grid-template-columns:70px 1fr auto 40px;align-items:center;gap:18px;padding:26px 8px;border-bottom:1px solid var(--line);transition:background .25s,padding .25s}
.irow:hover{background:var(--bg2);padding-left:20px}
.itit{font-size:clamp(26px,4vw,52px);transition:color .2s}
.irow:hover .itit{color:var(--red)}
.iarr{font-size:22px;color:var(--mut);transition:transform .25s,color .2s}
.irow:hover .iarr{transform:translateX(6px);color:var(--red)}
.chips{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:28px}
.chip{background:none;border:1px solid var(--line);color:var(--mut);font-size:11px;letter-spacing:.12em;text-transform:uppercase;padding:8px 14px;transition:all .2s}
.chip:hover{border-color:var(--red);color:var(--tx)}
.chip.on{background:var(--red);border-color:var(--red);color:#fff}
.masonry{columns:3;column-gap:14px}
.gitem{break-inside:avoid;margin-bottom:14px;cursor:zoom-in;overflow:hidden}
.gitem img{width:100%;object-fit:cover;transition:filter .3s,transform .5s}
.gitem:hover img{filter:saturate(1.2) contrast(1.06);transform:scale(1.012)}
.gitem figcaption{padding:7px 2px 0;background:var(--bg)}
.about{display:grid;grid-template-columns:.9fr 1.1fr;gap:60px;align-items:start}
.about .pic{position:sticky;top:100px}
.about .pic .kb{aspect-ratio:4/5;overflow:hidden}
.about .pic figcaption{padding-top:8px}
.about p{color:var(--mut);margin-bottom:18px;font-size:17px}
footer{border-top:1px solid var(--line);padding:22px 0}
.fbar{display:flex;justify-content:space-between;align-items:center;gap:20px;flex-wrap:wrap}
.fbar .left{display:flex;gap:16px;align-items:center;flex-wrap:wrap}
.fbar .right{display:flex;gap:14px;align-items:center}
@media(max-width:900px){
.hbar{padding:10px 16px}
nav{gap:14px}
nav a{font-size:10px}
#top{padding:96px 0 24px}
.hero-sub{grid-template-columns:1fr}
.masonry{columns:2}
.about{grid-template-columns:1fr}.about .pic{position:static;max-width:420px}
.fbar{flex-direction:column;align-items:flex-start;gap:14px}
}
@media(max-width:600px){
body{font-size:15px}
.wrap{padding:0 16px}
h1.big{font-size:clamp(36px,16vw,120px)}
.masonry{columns:1}
}
</style>
</head>
<body>
<div id="noise"></div>

<header>
  <div class="hbar">
    <a href="/" class="wordmark"><?= esc($settings['mark'] ?: 'NIXSHOOTS') ?><em>.</em></a>
    <nav>
      <a href="#index">Index</a>
      <a href="#series">Series</a>
      <a href="#statement">Statement</a>
      <a href="#about">About</a>
      <a href="#contact">Contact</a>
      <a href="/admin.php" class="hbook">CMS</a>
    </nav>
  </div>
</header>

<main>
<section id="top" class="wrap">
  <div class="hero-meta">
    <span class="mono"><span class="dot"></span><?= esc($settings['avail'] ?: 'Available for bookings') ?></span>
    <span class="mono"><?= esc($settings['loc'] ?: 'Worldwide') ?></span>
  </div>
  <h1 class="big">Photographs<br>that move</h1>
  <div class="hero-sub">
    <div class="tag"><?= esc($settings['tagline'] ?: 'Lookbooks • Stages • Skate • Streets • Editorial') ?></div>
    <div class="hero-lines">
      <div class="mono">Flash-first photography capturing raw moments with intentional imperfection.</div>
      <div class="mono">Printed small. Delivered fast. Made by one person.</div>
    </div>
  </div>
  <div class="scrollcue">
    <span class="mono">SCROLL TO EXPLORE</span>
    <span class="mono">↓ ↓ ↓</span>
  </div>
</section>

<div class="strip paused" id="tmarq">
  <div class="holes"></div>
  <div class="strip-track">
    <?php for($i=0;$i<12;$i++): ?>
    <div class="sframe">
      <div class="sfim ph" data-ph="loading frame <?= $i+1 ?>"><img src="" alt="" class="pimg" loading="lazy"></div>
      <span class="mono">Frame <?= str_pad($i+1, 3, '0', STR_PAD_LEFT) ?></span>
    </div>
    <?php endfor; ?>
  </div>
</div>

<section id="idx" class="wrap">
  <div class="shead"><span class="mono no">§ 00</span><h2>Index</h2><span class="lbl mono">All collections</span></div>
  <?php foreach($collections as $idx => $col): ?>
  <a href="#series" class="irow" data-col="<?= esc($col['id']) ?>">
    <span class="mono no"><?= sprintf('§ %02d', $idx + 1) ?></span>
    <span class="itit display"><?= esc($col['title']) ?></span>
    <span class="mono"><?= esc($col['year']) ?></span>
    <span class="iarr">→</span>
  </a>
  <?php endforeach; ?>
</section>

<section id="series" class="wrap">
  <div class="shead"><span class="mono no">§ 01</span><h2>Series</h2><span class="lbl mono">Curated collections</span></div>
  
  <div class="chips" id="chipz">
    <button class="chip on" data-f="all">All</button>
    <?php foreach($collections as $col): ?>
    <button class="chip" data-f="<?= esc($col['code']) ?>"><?= esc($col['code']) ?></button>
    <?php endforeach; ?>
  </div>
  
  <div class="masonry" id="msnry">
    <?php foreach($collections as $col): 
      $images = get_collection_images($col['id']);
      foreach($images as $img):
        $blur = blur_placeholder(min($img['width'], 400), min($img['height'], 500));
    ?>
    <figure class="gitem" data-col="<?= esc($col['code']) ?>">
      <div class="ph" data-ph="loading" style="--ph:<?= $blur ?>">
        <picture>
          <?php if ($img['webp_src']): ?>
          <source srcset="<?= esc($img['webp_src']) ?>" type="image/webp">
          <?php endif; ?>
          <img src="<?= esc($img['src']) ?>" alt="<?= esc($img['caption'] ?: 'Photograph') ?>" class="pimg" loading="lazy" decoding="async" width="<?= $img['width'] ?>" height="<?= $img['height'] ?>">
        </picture>
      </div>
      <figcaption class="mono"><?= esc($img['caption']) ?></figcaption>
    </figure>
    <?php endforeach; endforeach; ?>
  </div>
</section>

<section id="statement" class="wrap">
  <div class="shead"><span class="mono no">§ 02</span><h2>Statement</h2><span class="lbl mono">The approach</span></div>
  <p style="font-family:'Fraunces';font-style:italic;font-size:clamp(20px,2.5vw,28px);line-height:1.4;max-width:700px">
    <?= nl2br(esc($settings['statement'] ?: 'Photography that captures the moment between moments. Flash-lit honesty in a filtered world.')) ?>
  </p>
</section>

<section id="about" class="wrap">
  <div class="shead"><span class="mono no">§ 03</span><h2>About</h2><span class="lbl mono">The person behind the flash</span></div>
  <div class="about">
    <div class="pic">
      <div class="kb ph" data-ph="loading portrait">
        <img src="" alt="Portrait" class="pimg" loading="lazy">
      </div>
      <figcaption class="mono">© <?= date('Y') ?> <?= esc($settings['mark'] ?: 'NIXSHOOTS') ?></figcaption>
    </div>
    <div id="aboutP">
      <?php 
      $aboutText = $aboutPage ? $aboutPage['content'] : ($settings['about'] ?: 'nixshoots shoots everything that moves.');
      foreach(explode("\n\n", $aboutText) as $para):
      ?>
      <p><?= esc($para) ?></p>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section id="contact" class="wrap">
  <div class="shead"><span class="mono no">§ 04</span><h2>Contact</h2><span class="lbl mono">Let's make something</span></div>
  <div class="mono" style="display:grid;gap:10px;max-width:400px">
    <div>EMAIL &nbsp;<b><a href="mailto:<?= esc($settings['email'] ?: 'hello@nixshoots.com') ?>"><?= esc($settings['email'] ?: 'hello@nixshoots.com') ?></a></b></div>
    <div>WA &nbsp;<b><?= esc($settings['wa'] ?: '+0 000 000 0000') ?></b></div>
    <div>IG &nbsp;<b><a href="https://instagram.com/<?= str_replace('@', '', $settings['handle'] ?: 'nixshoots') ?>" target="_blank" rel="noopener"><?= esc($settings['handle'] ?: '@nixshoots') ?></a></b></div>
  </div>
</section>
</main>

<footer>
  <div class="wrap fbar">
    <div class="left">
      <span class="mono">© <?= date('Y') ?> <?= esc($settings['mark'] ?: 'NIXSHOOTS') ?></span>
      <?php if ($footerPage && $footerPage['content']): ?>
      <span class="mono"><?= nl2br(esc($footerPage['content'])) ?></span>
      <?php endif; ?>
    </div>
    <div class="right mono">
      <span>v<?= DB_VERSION ?></span>
      <span>•</span>
      <a href="/admin.php">CMS</a>
    </div>
  </div>
</footer>

<script>
const $=(sel,ctx=document)=>ctx.querySelector(sel);
const $$=(sel,ctx=document)=>[...ctx.querySelectorAll(sel)];
const DB={collections:<?= json_encode($collections) ?>};
const VERSION=<?= json_encode(['version' => DB_VERSION, 'cache_version' => CACHE_VERSION]) ?>;

// Image lazy loading
const ioP=new IntersectionObserver(e=>e.forEach(x=>{if(x.isIntersecting){const im=$('.pimg',x.target);if(im&&im.dataset.src){im.src=im.dataset.src;im.parentElement.classList.add('ld')}ioP.unobserve(x.target)}}),{rootMargin:'100px'});
$$('.ph[data-ph]').forEach(x=>ioP.observe(x));

// Collection filter
$('#chipz')?.addEventListener('click',e=>{if(!e.target.matches('.chip'))return;
 $$('.chip').forEach(c=>c.classList.remove('on'));e.target.classList.add('on');
 const f=e.target.dataset.f;
 $$('.gitem').forEach(g=>g.style.display=f==='all'||g.dataset.col===f?'block':'none')});

// Cache busting
fetch('/api.php?action=version').then(r=>r.json()).then(v=>{
  if(v.cache_version!==VERSION.cache_version){localStorage.clear();location.reload(true)}
}).catch(()=>{});
</script>
</body>
</html>
<?php
$content = ob_get_clean();
if (!$isVercel) cache_set('index_page', $content);
echo $content;
?>
