<?php
use Omega\Core\Security;
$e = [Security::class, 'e'];
$news = $news ?? [];
$topLevel = $topLevel ?? [];
$topReset = $topReset ?? [];
$nameRenderer = $nameRenderer ?? null;
?>
<section class="hero">
    <div class="hero-overlay"></div>
    <div class="container hero-content">
        <div class="hero-copy">
            <span class="eyebrow">MU ONLINE · 0.97K</span>
            <h1>El mundo de MU<br><em>vuelve a sus raíces.</em></h1>
            <p>Una experiencia clásica, competitiva y estable. Crea tu cuenta, forma tu guild y conquista el ranking.</p>
            <div class="hero-actions">
                <a class="btn btn-primary" href="/register">Crear cuenta</a>
                <a class="btn btn-ghost" href="/downloads">Descargar cliente</a>
            </div>
            <div class="hero-meta">
                <span><b><?= number_format((int)($stats['online'] ?? 0)) ?></b> Online</span>
                <span><b><?= number_format((int)($stats['accounts'] ?? 0)) ?></b> Cuentas</span>
                <span><b><?= number_format((int)($stats['guilds'] ?? 0)) ?></b> Guilds</span>
            </div>
        </div>
        <div class="hero-panel">
            <div class="hero-panel-title">Estado del Reino</div>
            <div class="hero-status-row"><span>GameServer</span><strong class="online">ONLINE</strong></div>
            <div class="hero-status-row"><span>Versión</span><strong>0.97k</strong></div>
            <div class="hero-status-row"><span>Database</span><strong>MuOnline97</strong></div>
            <div class="hero-status-row"><span>Conectados</span><strong><?= number_format((int)($stats['online'] ?? 0)) ?></strong></div>
            <a class="panel-link" href="/info">Ver información del servidor →</a>
        </div>
    </div>
</section>

<section class="section">
<div class="container">
    <div class="section-heading">
        <div><span class="eyebrow">ACTUALIDAD</span><h2>Últimas noticias</h2></div>
        <a href="/news">Ver todas →</a>
    </div>

    <?php if (!$news): ?>
    <div class="empty-state">
        <span class="empty-icon">Ω</span>
        <h3>Omega MU CMS ya está listo</h3>
        <p>Tu sistema de noticias está conectado. Cuando publiques la primera noticia desde el AdminCP aparecerá aquí.</p>
    </div>
    <?php else: ?>
    <div class="news-grid">
        <?php foreach ($news as $n): ?>
        <article class="news-card <?= !empty($n['featured'])?'featured':'' ?>">
            <div class="news-art">
                <span><?= $e(mb_strtoupper((string)($n['category'] ?: 'NOTICIA'))) ?></span>
            </div>
            <div class="news-content">
                <div class="news-date"><?= $e(date('d.m.Y', strtotime((string)($n['published_at'] ?: $n['created_at'])))) ?></div>
                <h3><a href="/news/<?= rawurlencode((string)$n['slug']) ?>"><?= $e($n['title']) ?></a></h3>
                <p><?= $e($n['summary'] ?: mb_substr(strip_tags((string)$n['content']), 0, 150).'…') ?></p>
                <a class="read-more" href="/news/<?= rawurlencode((string)$n['slug']) ?>">Leer noticia →</a>
            </div>
        </article>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>
</section>

<section class="section section-dark">
<div class="container">
    <div class="section-heading">
        <div><span class="eyebrow">COMPETENCIA</span><h2>Hall of Fame</h2></div>
        <a href="/rankings">Todos los rankings →</a>
    </div>
    <div class="ranking-columns">
        <div class="ranking-card">
            <div class="ranking-card-head"><h3>Top Nivel</h3><span>LEVEL</span></div>
            <div class="ranking-list">
            <?php foreach ($topLevel as $i=>$char): ?>
                <a class="rank-row" href="/rankings">
                    <span class="rank-pos"><?= $i+1 ?></span>
                    <span class="rank-avatar class-<?= (int)$char['Class'] ?>"><?= $e(substr((string)$char['Name'],0,1)) ?></span>
                    <span class="rank-name"><?= $nameRenderer?$nameRenderer->render((string)$char['Name'],['area'=>'home-ranking','class'=>(int)($char['Class']??-1)]):$e($char['Name']) ?><small><?= $e(\Omega\GameServer\Drivers\MuEmu097kKayito\CharacterClass::name((int)$char['Class'])) ?></small></span>
                    <strong><?= number_format((int)$char['cLevel']) ?></strong>
                </a>
            <?php endforeach; ?>
            </div>
        </div>

        <div class="ranking-card">
            <div class="ranking-card-head"><h3>Top Reset</h3><span>RESETS</span></div>
            <div class="ranking-list">
            <?php foreach ($topReset as $i=>$char): ?>
                <a class="rank-row" href="/rankings?type=reset">
                    <span class="rank-pos"><?= $i+1 ?></span>
                    <span class="rank-avatar class-<?= (int)$char['Class'] ?>"><?= $e(substr((string)$char['Name'],0,1)) ?></span>
                    <span class="rank-name"><?= $nameRenderer?$nameRenderer->render((string)$char['Name'],['area'=>'home-ranking','class'=>(int)($char['Class']??-1)]):$e($char['Name']) ?><small>Lv. <?= number_format((int)$char['cLevel']) ?></small></span>
                    <strong><?= number_format((int)$char['ResetCount']) ?></strong>
                </a>
            <?php endforeach; ?>
            </div>
        </div>

        <aside class="quick-card">
            <span class="eyebrow">COMIENZA AHORA</span>
            <h3>Tu leyenda empieza en Lorencia.</h3>
            <p>Regístrate, descarga el cliente y entra al servidor.</p>
            <a class="btn btn-primary full" href="/register">Crear cuenta</a>
            <a class="btn btn-ghost full" href="/downloads">Descargas</a>
        </aside>
    </div>
</div>
</section>

<section class="section">
<div class="container feature-strip">
    <div><span>⚔</span><strong>Old School PvP</strong><small>Combate clásico 0.97k</small></div>
    <div><span>🏰</span><strong>Guild System</strong><small>Compite por el primer lugar</small></div>
    <div><span>✦</span><strong>Eventos</strong><small>Blood Castle & Devil Square</small></div>
    <div><span>🛡</span><strong>CMS Seguro</strong><small>PDO, CSRF y módulos aislados</small></div>
</div>
</section>
