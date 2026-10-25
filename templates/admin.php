<?php
use Omega\Core\Security;
$e=[Security::class,'e'];
$admin=$admin??[]; $stats=$stats??[]; $newsCount=$newsCount??0; $links=$links??[];
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>AdminCP · Omega MU CMS v0.5.1</title>
<link rel="stylesheet" href="/public/assets/css/omega.css?v=050">
</head>
<body>
<section class="admin-shell">
<div class="admin-sidebar">
    <a class="admin-logo" href="/admin"><span>Ω</span><div><strong>OMEGA</strong><small>ADMIN CONTROL</small></div></a>
    <nav>
        <a class="active" href="/admin">◈ Dashboard</a>
        <a href="/admin/news">▤ Noticias <em>pronto</em></a>
        <a href="/admin/downloads">⇩ Descargas <em>pronto</em></a>
        <a href="/admin/accounts">♙ Cuentas <em>pronto</em></a>
        <a href="/admin/modules">⬡ Módulos <em>pronto</em></a>
        <a href="/admin/settings">⚙ Configuración <em>pronto</em></a>
    </nav>
    <div class="admin-side-bottom">
        <a href="/account">← Volver a mi cuenta</a>
        <a href="/">Ver sitio público</a>
    </div>
</div>

<div class="admin-main">
<header class="admin-topbar">
    <div><span class="eyebrow">ADMINCP</span><h1>Dashboard</h1></div>
    <div class="admin-user"><span><?= $e($admin['role']??'superadmin') ?></span><strong><?= $e($admin['account_id']??'') ?></strong></div>
</header>

<?php foreach($links as $link): if(!(int)$link['game_account_exists']): ?>
<div class="form-alert error wide">El administrador <b><?= $e($link['username']) ?></b> todavía no tiene una cuenta MEMB_INFO vinculada. Crea esa cuenta con el mismo ID para habilitar su acceso.</div>
<?php endif; endforeach; ?>

<div class="admin-stat-grid">
    <div><span>Jugadores online</span><strong><?= number_format((int)($stats['online']??0)) ?></strong><small>MEMB_STAT</small></div>
    <div><span>Cuentas</span><strong><?= number_format((int)($stats['accounts']??0)) ?></strong><small>MEMB_INFO</small></div>
    <div><span>Personajes</span><strong><?= number_format((int)($stats['characters']??0)) ?></strong><small>Character</small></div>
    <div><span>Guilds</span><strong><?= number_format((int)($stats['guilds']??0)) ?></strong><small>Guild</small></div>
</div>

<div class="admin-grid">
<section class="admin-panel">
    <div class="admin-panel-head"><div><span class="eyebrow">CMS</span><h2>Estado de Omega</h2></div><span class="admin-ok">OPERATIVO</span></div>
    <div class="admin-status-list">
        <div><span>Base MU</span><strong>MuOnline97</strong></div>
        <div><span>Driver SQL</span><strong>PDO_DBLIB / FreeTDS</strong></div>
        <div><span>Administrador asociado</span><strong><?= $e($admin['account_id']??'') ?></strong></div>
        <div><span>Noticias publicadas</span><strong><?= number_format((int)$newsCount) ?></strong></div>
    </div>
</section>

<section class="admin-panel">
    <div class="admin-panel-head"><div><span class="eyebrow">PERMISOS</span><h2>Identidad administrativa</h2></div></div>
    <div class="admin-identity">
        <div class="account-avatar">Ω</div>
        <div><strong><?= $e($admin['account_id']??'') ?></strong><span><?= $e($admin['email']??'') ?></span><small>Rol: <?= $e($admin['role']??'superadmin') ?></small></div>
    </div>
    <div class="notice-box">El AdminCP no posee una contraseña independiente. El acceso depende de que esta cuenta esté autenticada en el Account Panel y figure activa en <code>cms_admin_users</code>.</div>
</section>
</div>

<section class="admin-panel admin-next">
    <div class="admin-panel-head"><div><span class="eyebrow">SIGUIENTE FASE</span><h2>Herramientas administrativas</h2></div></div>
    <div class="admin-module-grid">
        <div><span>▤</span><strong>Noticias</strong><small>Crear, editar, destacar y programar.</small></div>
        <div><span>♙</span><strong>Cuentas</strong><small>Buscar cuentas y revisar estado.</small></div>
        <div><span>⇩</span><strong>Descargas</strong><small>Administrar cliente, parche y mirrors.</small></div>
        <div><span>⬡</span><strong>Module Manager</strong><small>Activar y configurar módulos.</small></div>
    </div>
</section>
</div>
</section>

</body></html>
