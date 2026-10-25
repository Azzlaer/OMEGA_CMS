<?php
use Omega\Core\Security;
$e = [Security::class, 'e'];
$title = $title ?? 'Omega MU';
$active = $active ?? 'home';
$body = $body ?? '';
$stats = $stats ?? ['online'=>0,'accounts'=>0,'characters'=>0,'guilds'=>0];
$appName = $appName ?? 'Omega MU';
$serverVersion = $serverVersion ?? 'MuEmu 0.97k';
$loggedAccount = $loggedAccount ?? null;
$isAdmin = $isAdmin ?? false;
$moduleHeadHtml = $moduleHeadHtml ?? '';

$design=$design??[];
$ds=$design['settings']??[];
$dsPage=$design['page']??[];
$dsHero=$design['hero']??[];

$cssVars=[
    '--bg'=>(string)($ds['theme_bg']??'#090b0f'),
    '--panel'=>(string)($ds['theme_panel']??'#141820'),
    '--text'=>(string)($ds['theme_text']??'#f4f0e8'),
    '--line'=>(string)($ds['theme_border']??'#282f3b'),
    '--gold'=>(string)($ds['theme_accent']??'#d5a33d'),
    '--gold2'=>(string)($ds['theme_accent2']??'#f2c565'),
    '--omega-radius'=>((int)($ds['theme_radius']??10)).'px',
];
$cssVarText='';
foreach($cssVars as $k=>$v){ $cssVarText.=$k.':'.$v.';'; }

$pageUrls=$dsPage['urls']??[];
$pageUrl=(string)($pageUrls[0]??'');
$pageOverlay=max(0,min(90,(int)($dsPage['overlay']??46)));
$pagePosition=(string)($dsPage['position']??'center center');
$pageBlur=max(0,min(12,(int)($dsPage['blur']??0)));
$pageFixed=!empty($dsPage['fixed']);

?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="theme-color" content="#090b0f">
<title><?= $e($title) ?> · <?= $e($appName) ?></title>
<meta name="description" content="<?= $e($appName) ?> - Comunidad MU Online clásica 0.97k">
<link rel="stylesheet" href="/public/assets/css/omega.css?v=240">
<script defer src="/public/assets/js/omega.js?v=240"></script>
<?= $moduleHeadHtml ?>
<style id="omega-design-vars">
:root{<?= $e($cssVarText) ?>}
</style>
</head>
<body class="<?= !empty($dsHero['enabled'])?'design-hero-active':'' ?>"
      style="<?= $e($cssVarText) ?>">
<div class="page-bg design-page-bg"
     aria-hidden="true"
     <?php if(in_array(($dsPage['type']??'default'),['image','random'],true) && $pageUrl!==''): ?>
     style="background-image:linear-gradient(rgba(0,0,0,<?= $pageOverlay/100 ?>),rgba(0,0,0,<?= $pageOverlay/100 ?>)),url('<?= $e($pageUrl) ?>');background-position:<?= $e($pagePosition) ?>;background-attachment:<?= $pageFixed?'fixed':'scroll' ?>;filter:blur(<?= $pageBlur ?>px) scale(1.02)"
     <?php elseif(($dsPage['type']??'default')==='solid'): ?>
     style="background:var(--bg)"
     <?php endif; ?>></div>

<?php if(($dsPage['type']??'')==='video' && $pageUrl!==''): ?>
<div class="design-page-video-wrap" aria-hidden="true">
    <video class="design-page-video" autoplay muted loop playsinline>
        <source src="<?= $e($pageUrl) ?>">
    </video>
    <div class="design-page-video-overlay" style="background:rgba(0,0,0,<?= $pageOverlay/100 ?>)"></div>
</div>
<?php endif; ?>
<header class="site-header" style="--omega-hero-height:<?= (int)($dsHero['height']??300) ?>px">
<?php if(!empty($dsHero['enabled']) && !empty($dsHero['url'])): ?>
    <div class="design-header-media" aria-hidden="true">
        <?php if(($dsHero['mode']??'')==='video'): ?>
            <video autoplay muted loop playsinline
                   style="object-position:<?= $e((string)($dsHero['position']??'center center')) ?>;filter:blur(<?= (int)($dsHero['blur']??0) ?>px) scale(1.02)">
                <source src="<?= $e((string)$dsHero['url']) ?>">
            </video>
        <?php else: ?>
            <div class="design-header-image"
                 style="background-image:url('<?= $e((string)$dsHero['url']) ?>');background-position:<?= $e((string)($dsHero['position']??'center center')) ?>;filter:blur(<?= (int)($dsHero['blur']??0) ?>px) scale(1.02)"></div>
        <?php endif; ?>
        <div class="design-header-overlay"
             style="background:rgba(0,0,0,<?= max(0,min(90,(int)($dsHero['overlay']??52)))/100 ?>)"></div>
    </div>
<?php endif; ?>
    <div class="topline">
        <div class="container topbar">
            <span class="server-dot"></span>
            <strong>Servidor <?= ((int)($stats['online'] ?? 0) >= 0) ? 'ONLINE' : 'OFFLINE' ?></strong>
            <span class="muted">·</span>
            <span><?= number_format((int)($stats['online'] ?? 0)) ?> jugadores conectados</span>
            <div class="top-links">
                <?php if($loggedAccount): ?>
                    <a href="/account">⚔ <?= $e($loggedAccount) ?></a>
                    <?php if($isAdmin): ?><a class="admin-top-link" href="/admin">⚙ Panel Admin</a><?php endif; ?>
                    <form class="inline-logout" method="post" action="/logout"><input type="hidden" name="_csrf" value="<?= $e(\Omega\Core\Csrf::token()) ?>"><button type="submit">Salir</button></form>
                <?php else: ?>
                    <a href="/login">Mi Cuenta</a>
                    <a href="/register">Crear Cuenta</a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="container brand-row">
        <a class="brand" href="/">
            <span class="brand-mark">Ω</span>
            <span>
                <strong><?= $e($appName) ?></strong>
                <small><?= $e($serverVersion) ?> · Old School Experience</small>
            </span>
        </a>
        <div class="brand-status">
            <div><span>ONLINE</span><strong><?= number_format((int)($stats['online'] ?? 0)) ?></strong></div>
            <div><span>CUENTAS</span><strong><?= number_format((int)($stats['accounts'] ?? 0)) ?></strong></div>
            <div><span>PERSONAJES</span><strong><?= number_format((int)($stats['characters'] ?? 0)) ?></strong></div>
        </div>
    </div>

    <nav class="main-nav" aria-label="Navegación principal">
        <div class="container nav-inner">
            <button class="nav-toggle" type="button" aria-label="Abrir menú">☰</button>
            <div class="nav-links">
                <a class="<?= $active==='home'?'active':'' ?>" href="/">Inicio</a>
                <a class="<?= $active==='news'?'active':'' ?>" href="/news">Noticias</a>
                <a class="<?= $active==='guides'?'active':'' ?>" href="/guides">Guías</a>
                <a class="<?= $active==='changelog'?'active':'' ?>" href="/changelog">Changelog</a>
                <a class="<?= $active==='rankings'?'active':'' ?>" href="/rankings">Rankings</a>
                <a class="<?= $active==='online'?'active':'' ?>" href="/online">Online</a>
                <a class="<?= $active==='downloads'?'active':'' ?>" href="/downloads">Descargas</a>
                <a class="<?= $active==='info'?'active':'' ?>" href="/info">Información</a>
                <a class="<?= $active==='account'?'active':'' ?>" href="<?= $loggedAccount?'/account':'/login' ?>">Mi Cuenta</a>
            </div>
        </div>
    </nav>
</header>

<main>
<?= $body ?>
</main>

<footer class="site-footer">
    <div class="container footer-grid">
        <div>
            <div class="footer-brand"><span>Ω</span> <?= $e($appName) ?></div>
            <p>MU Online clásico sobre MuEmu 0.97k Kayito. Web protegida por Omega MU CMS 2.0.</p>
        </div>
        <div>
            <h4>Servidor</h4>
            <a href="/info">Información</a>
            <a href="/rankings">Rankings</a>
            <a href="/guides">Guías</a>
            <a href="/downloads">Descargas</a>
        </div>
        <div>
            <h4>Cuenta</h4>
            <a href="/register">Crear cuenta</a>
            <a href="/login">Ingresar</a>
            <a href="/account/recovery">Recuperar contraseña</a>
        </div>
        <div>
            <h4>Estado</h4>
            <p><span class="server-dot"></span> GameServer conectado</p>
            <p><?= number_format((int)($stats['guilds'] ?? 0)) ?> guilds registradas</p>
        </div>
    </div>
    <div class="footer-bottom">
        <div class="container">© <?= date('Y') ?> <?= $e($appName) ?> · Omega MU CMS 2.0 v0.24.0</div>
    </div>
</footer>
<?php if(($dsPage['type']??'')==='random' && count($pageUrls)>1 && (int)($dsPage['rotation']??0)>0): ?>
<script>
document.addEventListener('DOMContentLoaded',function(){
    const bg=document.querySelector('.design-page-bg');
    if(!bg) return;
    const urls=<?= json_encode(array_values($pageUrls),JSON_UNESCAPED_SLASHES) ?>;
    const overlay=<?= json_encode($pageOverlay/100) ?>;
    const pos=<?= json_encode($pagePosition) ?>;
    let i=0;
    window.setInterval(function(){
        i=(i+1)%urls.length;
        bg.style.backgroundImage='linear-gradient(rgba(0,0,0,'+overlay+'),rgba(0,0,0,'+overlay+')),url("'+urls[i].replace(/"/g,'\\"')+'")';
        bg.style.backgroundPosition=pos;
    },<?= max(5,(int)$dsPage['rotation']) ?>*1000);
});
</script>
<?php endif; ?>
</body>
</html>