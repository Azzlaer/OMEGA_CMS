<?php
use Omega\Core\Security;

$e=[Security::class,'e'];
$admin=$admin??[];
$activeAdmin=$activeAdmin??'dashboard';
$adminBody=$adminBody??'';

$isSuper=strtolower((string)($admin['role']??''))==='superadmin';

$groups=[
    'overview'=>[
        'label'=>'OPERACIÓN',
        'items'=>[
            ['dashboard','/admin','◈','Dashboard'],
            ['accounts','/admin/accounts','♙','Cuentas'],
            ['ranking-exclusions','/admin/ranking-exclusions','🚫','Excluir Rankings'],
        ],
    ],
    'content'=>[
        'label'=>'CONTENIDO',
        'items'=>[
            ['news','/admin/news','▤','Noticias'],
            ['guides','/admin/guides','📚','Guías'],
            ['changelog','/admin/changelog','↻','Changelog'],
            ['downloads','/admin/downloads','⇩','Descargas'],
        ],
    ],
    'systems'=>[
        'label'=>'SISTEMAS',
        'items'=>[
            ['modules','/admin/modules','⬡','Módulos'],
            ['database-features','/admin/database-features','🧬','DB Feature Center'],
            ['visuals','/admin/visual-assets','▣','Iconos & Variables'],
        ],
    ],
    'platform'=>[
        'label'=>'PLATAFORMA',
        'items'=>[
            ['design-studio','/admin/design-studio','🎨','Visual Design Studio','super'],
            ['access','/admin/access','♛','Acceso Admin','super'],
            ['settings','/admin/settings','⚙','Configuración'],
        ],
    ],
];

$currentGroup='overview';
foreach($groups as $gk=>$group){
    foreach($group['items'] as $item){
        if($item[0]===$activeAdmin){$currentGroup=$gk;break 2;}
    }
}
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="color-scheme" content="dark">
<title><?= $e($adminTitle??'AdminCP') ?> · Omega MU CMS</title>
<link rel="stylesheet" href="/public/assets/css/omega.css?v=250">
</head>
<body class="omega-admin-enterprise">

<section class="admin-shell admin-enterprise-shell" id="enterpriseAdmin">

    <aside class="admin-sidebar admin-enterprise-sidebar" id="adminSidebar">
        <div class="admin-brand-zone">
            <a class="admin-logo admin-enterprise-logo" href="/admin">
                <span class="admin-brand-symbol">Ω</span>
                <div>
                    <strong>OMEGA</strong>
                    <small>ENTERPRISE CONTROL</small>
                </div>
            </a>

            <button class="admin-sidebar-close" type="button" id="adminSidebarClose"
                    aria-label="Cerrar menú">×</button>
        </div>

        <div class="admin-environment-card">
            <div>
                <span class="admin-pulse"></span>
                <strong>CMS ONLINE</strong>
            </div>
            <small>MuOnline97 · Omega Core</small>
        </div>

        <nav class="admin-enterprise-nav" aria-label="Administración">
            <?php foreach($groups as $groupKey=>$group): ?>
                <div class="admin-nav-group <?= $currentGroup===$groupKey?'group-current':'' ?>">
                    <div class="admin-nav-label"><?= $e($group['label']) ?></div>

                    <?php foreach($group['items'] as $item):
                        [$key,$href,$icon,$label]=$item;
                        $requiresSuper=($item[4]??'')==='super';
                        if($requiresSuper && !$isSuper) continue;
                    ?>
                    <a class="admin-nav-item <?= $activeAdmin===$key?'active':'' ?>"
                       href="<?= $e($href) ?>">
                        <span class="admin-nav-icon"><?= $e($icon) ?></span>
                        <span class="admin-nav-copy">
                            <strong><?= $e($label) ?></strong>
                            <small><?=
                                $key==='dashboard'?'Resumen general':
                                ($key==='accounts'?'Usuarios y cuentas':
                                ($key==='news'?'Publicaciones':
                                ($key==='guides'?'Biblioteca del servidor':
                                ($key==='changelog'?'Actualizaciones':
                                ($key==='downloads'?'Archivos del cliente':
                                ($key==='modules'?'Extensiones instalables':
                                ($key==='database-features'?'Datos del GameServer':
                                ($key==='design-studio'?'Apariencia del sitio':
                                ($key==='access'?'Permisos administrativos':
                                ($key==='visuals'?'Recursos visuales':
                                ($key==='ranking-exclusions'?'Filtros públicos':'Opciones del CMS')))))))))))
                            ?></small>
                        </span>
                        <span class="admin-nav-chevron">›</span>
                    </a>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        </nav>

        <div class="admin-sidebar-footer">
            <div class="admin-session-card">
                <div class="admin-avatar"><?= $e(strtoupper(substr((string)($admin['account_id']??'A'),0,1))) ?></div>
                <div>
                    <strong><?= $e((string)($admin['account_id']??'')) ?></strong>
                    <small><?= $e(strtoupper((string)($admin['role']??'admin'))) ?></small>
                </div>
            </div>

            <div class="admin-sidebar-actions">
                <a href="/account">👤 Mi Cuenta</a>
                <a href="/" target="_blank" rel="noopener">↗ Sitio público</a>
            </div>
        </div>
    </aside>

    <div class="admin-mobile-overlay" id="adminMobileOverlay"></div>

    <main class="admin-main admin-enterprise-main">
        <header class="admin-topbar admin-enterprise-topbar">
            <div class="admin-topbar-left">
                <button class="admin-mobile-menu" type="button" id="adminMenuButton"
                        aria-label="Abrir menú">☰</button>
                <div>
                    <div class="admin-breadcrumb">
                        OMEGA ADMIN <span>/</span>
                        <?= $e(strtoupper((string)($groups[$currentGroup]['label']??'CONTROL'))) ?>
                    </div>
                    <h1><?= $e($adminTitle??'Dashboard') ?></h1>
                </div>
            </div>

            <div class="admin-topbar-right">
                <a class="admin-top-action" href="/admin/design-studio"
                   title="Visual Design Studio">🎨</a>
                <a class="admin-top-action" href="/admin/settings"
                   title="Configuración">⚙</a>

                <div class="admin-top-status">
                    <span class="admin-pulse"></span>
                    <div>
                        <small>SISTEMA</small>
                        <strong>OPERATIVO</strong>
                    </div>
                </div>

                <div class="admin-user admin-user-enterprise">
                    <div class="admin-avatar small"><?= $e(strtoupper(substr((string)($admin['account_id']??'A'),0,1))) ?></div>
                    <div>
                        <span><?= $e((string)($admin['role']??'admin')) ?></span>
                        <strong><?= $e((string)($admin['account_id']??'')) ?></strong>
                    </div>
                </div>
            </div>
        </header>

        <div class="admin-content-frame">
            <?= $adminBody ?>
        </div>

        <footer class="admin-enterprise-footer">
            <span>Ω Omega MU CMS · Enterprise Admin</span>
            <span>Panel administrativo seguro</span>
        </footer>
    </main>
</section>

<script>
(function(){
    const root=document.getElementById('enterpriseAdmin');
    const openBtn=document.getElementById('adminMenuButton');
    const closeBtn=document.getElementById('adminSidebarClose');
    const overlay=document.getElementById('adminMobileOverlay');

    if(!root) return;

    function openSidebar(){
        root.classList.add('sidebar-open');
        if(openBtn) openBtn.setAttribute('aria-expanded','true');
    }

    function closeSidebar(){
        root.classList.remove('sidebar-open');
        if(openBtn) openBtn.setAttribute('aria-expanded','false');
    }

    if(openBtn) openBtn.addEventListener('click',openSidebar);
    if(closeBtn) closeBtn.addEventListener('click',closeSidebar);
    if(overlay) overlay.addEventListener('click',closeSidebar);

    document.addEventListener('keydown',function(e){
        if(e.key==='Escape') closeSidebar();
    });
})();
</script>
</body>
</html>
