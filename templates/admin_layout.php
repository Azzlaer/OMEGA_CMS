<?php
use Omega\Core\Security;
use Omega\Core\Csrf;

$e=[Security::class,'e'];
$admin=$admin??[];
$activeAdmin=$activeAdmin??'dashboard';
$adminBody=$adminBody??'';
$isSuper=strtolower((string)($admin['role']??''))==='superadmin';

$groups=[
    'overview'=>[
        'label'=>'Operación',
        'icon'=>'◈',
        'items'=>[
            ['dashboard','/admin','⌂','Dashboard','Resumen general'],
            ['accounts','/admin/accounts','♙','Cuentas','Usuarios y cuentas'],
            ['ranking-exclusions','/admin/ranking-exclusions','⊘','Excluir Rankings','Filtros públicos'],
        ],
    ],
    'content'=>[
        'label'=>'Contenido',
        'icon'=>'▤',
        'items'=>[
            ['news','/admin/news','▤','Noticias','Publicaciones'],
            ['guides','/admin/guides','▣','Guías','Biblioteca del servidor'],
            ['changelog','/admin/changelog','↻','Changelog','Actualizaciones'],
            ['downloads','/admin/downloads','⇩','Descargas','Archivos del cliente'],
        ],
    ],
    'systems'=>[
        'label'=>'Sistemas',
        'icon'=>'⬡',
        'items'=>[
            ['modules','/admin/modules','⬡','Módulos','Extensiones instalables'],
            ['database-features','/admin/database-features','⌗','DB Feature Center','Datos del GameServer'],
            ['visuals','/admin/visual-assets','▣','Iconos & Variables','Recursos visuales'],
        ],
    ],
    'platform'=>[
        'label'=>'Plataforma',
        'icon'=>'⚙',
        'items'=>[
            ['design-studio','/admin/design-studio','✦','Visual Design Studio','Apariencia del sitio','super'],
            ['monetization','/admin/monetization','◆','Google & Monetization','Google, Ads y privacidad','super'],
            ['access','/admin/access','♛','Acceso Admin','Permisos administrativos','super'],
            ['settings','/admin/settings','⚙','Configuración','Opciones del CMS'],
        ],
    ],
];

$currentGroup='overview';
foreach($groups as $gk=>$group){
    foreach($group['items'] as $item){
        if($item[0]===$activeAdmin){$currentGroup=$gk;break 2;}
    }
}

$adminName=(string)($admin['account_id']??'Admin');
$adminRole=strtoupper((string)($admin['role']??'admin'));
$initial=strtoupper(substr($adminName!==''?$adminName:'A',0,1));
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="color-scheme" content="dark">
<title><?= $e($adminTitle??'AdminCP') ?> · Omega MU CMS</title>
<link rel="stylesheet" href="/public/assets/css/omega.css?v=270">
</head>
<body class="omega-admin-enterprise">

<section class="admin-shell admin-enterprise-shell admin-v2-shell" id="enterpriseAdmin">

    <aside class="admin-sidebar admin-enterprise-sidebar admin-v2-sidebar" id="adminSidebar">
        <div class="admin-v2-brand">
            <a class="admin-v2-logo" href="/admin" aria-label="OMEGA Admin">
                <span class="admin-v2-logo-mark">Ω</span>
                <span class="admin-v2-logo-copy">
                    <strong>OMEGA</strong>
                    <small>Enterprise Control</small>
                </span>
            </a>
            <div class="admin-v2-brand-actions">
                <button class="admin-v2-icon-btn desktop-only" type="button" id="adminSidebarCompact"
                        title="Contraer menú" aria-label="Contraer menú">⇤</button>
                <button class="admin-v2-icon-btn mobile-only" type="button" id="adminSidebarClose"
                        aria-label="Cerrar menú">×</button>
            </div>
        </div>

        <div class="admin-v2-health">
            <span class="admin-pulse"></span>
            <span class="admin-v2-health-copy">
                <strong>CMS ONLINE</strong>
                <small>MuOnline97 · Omega Core</small>
            </span>
        </div>

        <div class="admin-v2-search-wrap">
            <span class="admin-v2-search-icon">⌕</span>
            <input id="adminMenuSearch" type="search" placeholder="Buscar sección..."
                   autocomplete="off" aria-label="Buscar en el menú">
            <kbd>/</kbd>
        </div>

        <nav class="admin-enterprise-nav admin-v2-nav" aria-label="Administración">
            <?php foreach($groups as $groupKey=>$group): ?>
                <?php
                $visibleItems=[];
                foreach($group['items'] as $item){
                    $requiresSuper=($item[5]??'')==='super';
                    if(!$requiresSuper || $isSuper) $visibleItems[]=$item;
                }
                if(!$visibleItems) continue;
                ?>
                <section class="admin-nav-group admin-v2-group <?= $currentGroup===$groupKey?'group-current is-open':'' ?>"
                         data-admin-group="<?= $e($groupKey) ?>">
                    <button class="admin-v2-group-toggle" type="button" data-group-toggle
                            aria-expanded="<?= $currentGroup===$groupKey?'true':'false' ?>">
                        <span class="admin-v2-group-icon"><?= $e($group['icon']) ?></span>
                        <span class="admin-v2-group-label"><?= $e($group['label']) ?></span>
                        <span class="admin-v2-group-count"><?= count($visibleItems) ?></span>
                        <span class="admin-v2-group-chevron">⌄</span>
                    </button>

                    <div class="admin-v2-group-items" data-group-items>
                        <div class="admin-v2-group-items-inner">
                        <?php foreach($visibleItems as $item):
                            [$key,$href,$icon,$label,$description]=$item;
                        ?>
                        <a class="admin-nav-item admin-v2-nav-item <?= $activeAdmin===$key?'active':'' ?>"
                           href="<?= $e($href) ?>" data-menu-item
                           data-search="<?= $e(strtolower($label.' '.$description.' '.$group['label'])) ?>">
                            <span class="admin-nav-icon admin-v2-nav-icon"><?= $e($icon) ?></span>
                            <span class="admin-nav-copy admin-v2-nav-copy">
                                <strong><?= $e($label) ?></strong>
                                <small><?= $e($description) ?></small>
                            </span>
                            <span class="admin-nav-chevron admin-v2-nav-arrow">›</span>
                        </a>
                        <?php endforeach; ?>
                        </div>
                    </div>
                </section>
            <?php endforeach; ?>

            <div class="admin-v2-no-results" id="adminMenuNoResults" hidden>
                No se encontraron secciones.
            </div>
        </nav>

        <div class="admin-sidebar-footer admin-v2-footer">
            <div class="admin-session-card admin-v2-session">
                <div class="admin-avatar admin-v2-avatar"><?= $e($initial) ?></div>
                <div class="admin-v2-session-copy">
                    <strong><?= $e($adminName) ?></strong>
                    <small><?= $e($adminRole) ?></small>
                </div>
                <span class="admin-v2-session-dot" title="Sesión activa"></span>
            </div>

            <div class="admin-sidebar-actions admin-v2-actions">
                <a href="/account" title="Mi cuenta">
                    <span>♙</span><b>Mi Cuenta</b>
                </a>
                <a href="/" target="_blank" rel="noopener" title="Sitio público">
                    <span>↗</span><b>Sitio público</b>
                </a>
            </div>

            <form method="post" action="/logout" class="admin-v2-logout"
                  onsubmit="return confirm('¿Cerrar la sesión administrativa?')">
                <input type="hidden" name="_csrf" value="<?= $e(Csrf::token()) ?>">
                <button type="submit" title="Cerrar sesión">
                    <span>⇥</span>
                    <b>Cerrar sesión</b>
                </button>
            </form>
        </div>
    </aside>

    <div class="admin-mobile-overlay" id="adminMobileOverlay"></div>

    <main class="admin-main admin-enterprise-main admin-v2-main">
        <header class="admin-topbar admin-enterprise-topbar admin-v2-topbar">
            <div class="admin-topbar-left admin-v2-topbar-left">
                <button class="admin-mobile-menu admin-v2-mobile-menu" type="button" id="adminMenuButton"
                        aria-label="Abrir menú">☰</button>
                <div>
                    <div class="admin-breadcrumb admin-v2-breadcrumb">
                        OMEGA ADMIN <span>›</span>
                        <?= $e(strtoupper((string)($groups[$currentGroup]['label']??'CONTROL'))) ?>
                    </div>
                    <h1><?= $e($adminTitle??'Dashboard') ?></h1>
                </div>
            </div>

            <div class="admin-topbar-right admin-v2-topbar-right">
                <a class="admin-top-action admin-v2-top-action" href="/" target="_blank" rel="noopener"
                   title="Abrir sitio público">↗</a>
                <?php if($isSuper): ?>
                <a class="admin-top-action admin-v2-top-action" href="/admin/design-studio"
                   title="Visual Design Studio">✦</a>
                <?php endif; ?>
                <a class="admin-top-action admin-v2-top-action" href="/admin/settings"
                   title="Configuración">⚙</a>

                <div class="admin-user admin-user-enterprise admin-v2-top-user">
                    <div class="admin-avatar small admin-v2-avatar"><?= $e($initial) ?></div>
                    <div>
                        <span><?= $e($adminRole) ?></span>
                        <strong><?= $e($adminName) ?></strong>
                    </div>
                </div>
            </div>
        </header>

        <div class="admin-content-frame admin-v2-content">
            <?= $adminBody ?>
        </div>

        <footer class="admin-enterprise-footer admin-v2-page-footer">
            <span>Ω Omega MU CMS · Enterprise Admin</span>
            <span>Sesión segura · <?= $e($adminName) ?></span>
        </footer>
    </main>
</section>

<script defer src="/public/assets/js/admin-navigation.js?v=271"></script>

</body>
</html>
