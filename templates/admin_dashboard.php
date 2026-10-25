<?php
use Omega\Core\Security;
$e=[Security::class,'e'];

$online=(int)($stats['online']??0);
$accounts=(int)($stats['accounts']??0);
$characters=(int)($stats['characters']??0);
$guilds=(int)($stats['guilds']??0);
?>
<section class="admin-command-hero">
    <div class="admin-command-copy">
        <span class="eyebrow">COMMAND CENTER</span>
        <h2>Centro de Control Omega</h2>
        <p>
            Supervisa el estado del servidor, administra contenido y accede
            rápidamente a las herramientas críticas del CMS.
        </p>
    </div>

    <div class="admin-command-state">
        <div class="admin-command-indicator">
            <span class="admin-pulse large"></span>
            <div>
                <small>ESTADO GENERAL</small>
                <strong>OPERATIVO</strong>
            </div>
        </div>
        <a class="btn btn-primary" href="/" target="_blank" rel="noopener">↗ Ver sitio</a>
    </div>
</section>

<div class="admin-stat-grid admin-enterprise-stat-grid">
    <article class="admin-stat-card accent-green">
        <div class="admin-stat-icon">🟢</div>
        <div>
            <span>Jugadores online</span>
            <strong><?= number_format($online) ?></strong>
            <small>MEMB_STAT · tiempo real</small>
        </div>
    </article>

    <article class="admin-stat-card accent-gold">
        <div class="admin-stat-icon">👥</div>
        <div>
            <span>Cuentas registradas</span>
            <strong><?= number_format($accounts) ?></strong>
            <small>MEMB_INFO</small>
        </div>
    </article>

    <article class="admin-stat-card accent-blue">
        <div class="admin-stat-icon">⚔</div>
        <div>
            <span>Personajes</span>
            <strong><?= number_format($characters) ?></strong>
            <small>Character</small>
        </div>
    </article>

    <article class="admin-stat-card accent-purple">
        <div class="admin-stat-icon">🏰</div>
        <div>
            <span>Guilds</span>
            <strong><?= number_format($guilds) ?></strong>
            <small>Guild</small>
        </div>
    </article>
</div>

<div class="admin-enterprise-layout">
    <section class="admin-panel admin-enterprise-panel">
        <div class="admin-panel-head">
            <div>
                <span class="eyebrow">INFRAESTRUCTURA</span>
                <h2>Estado de Omega</h2>
            </div>
            <span class="admin-ok">● ONLINE</span>
        </div>

        <div class="admin-system-health">
            <div class="admin-health-row">
                <div class="admin-health-icon">🗄</div>
                <div>
                    <span>Base de datos MU</span>
                    <strong>MuOnline97</strong>
                </div>
                <em>Conectada</em>
            </div>
            <div class="admin-health-row">
                <div class="admin-health-icon">🔌</div>
                <div>
                    <span>Driver SQL</span>
                    <strong>PDO_DBLIB / FreeTDS</strong>
                </div>
                <em>Activo</em>
            </div>
            <div class="admin-health-row">
                <div class="admin-health-icon">📰</div>
                <div>
                    <span>Noticias publicadas</span>
                    <strong><?= number_format((int)$newsCount) ?></strong>
                </div>
                <em>CMS</em>
            </div>
            <div class="admin-health-row">
                <div class="admin-health-icon">⬇</div>
                <div>
                    <span>Descargas activas</span>
                    <strong><?= number_format((int)$downloadsCount) ?></strong>
                </div>
                <em>Portal</em>
            </div>
        </div>
    </section>

    <section class="admin-panel admin-enterprise-panel">
        <div class="admin-panel-head">
            <div>
                <span class="eyebrow">QUICK ACCESS</span>
                <h2>Gestión rápida</h2>
            </div>
        </div>

        <div class="admin-quick-grid">
            <a href="/admin/news">
                <span>▤</span><div><strong>Noticias</strong><small>Crear y publicar novedades</small></div><b>›</b>
            </a>
            <a href="/admin/accounts">
                <span>♙</span><div><strong>Cuentas</strong><small>Buscar usuarios registrados</small></div><b>›</b>
            </a>
            <a href="/admin/modules">
                <span>⬡</span><div><strong>Módulos</strong><small>Instalar y configurar extensiones</small></div><b>›</b>
            </a>
            <a href="/admin/design-studio">
                <span>🎨</span><div><strong>Diseño</strong><small>Visual Design Studio</small></div><b>›</b>
            </a>
            <a href="/admin/database-features">
                <span>🧬</span><div><strong>Database Center</strong><small>Inspeccionar sistemas GameServer</small></div><b>›</b>
            </a>
            <a href="/admin/settings">
                <span>⚙</span><div><strong>Configuración</strong><small>Preferencias generales del CMS</small></div><b>›</b>
            </a>
        </div>
    </section>
</div>

<section class="admin-panel admin-enterprise-panel admin-activity-panel">
    <div class="admin-panel-head">
        <div>
            <span class="eyebrow">SERVER SNAPSHOT</span>
            <h2>Resumen operacional</h2>
        </div>
    </div>

    <div class="admin-operation-grid">
        <div>
            <span>Promedio de personajes por cuenta</span>
            <strong><?= $accounts>0?number_format($characters/$accounts,2,',','.'):'0,00' ?></strong>
        </div>
        <div>
            <span>Promedio de miembros potenciales por guild</span>
            <strong><?= $guilds>0?number_format($characters/$guilds,1,',','.'):'0,0' ?></strong>
        </div>
        <div>
            <span>Contenido editorial</span>
            <strong><?= number_format((int)$newsCount+(int)$downloadsCount) ?></strong>
        </div>
        <div>
            <span>Motor</span>
            <strong>MuEmu 0.97k</strong>
        </div>
    </div>
</section>
