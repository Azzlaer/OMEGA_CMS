<?php
use Omega\Core\Security;
use Omega\Services\GuideManager;
$e=[Security::class,'e'];
$guide=$guide??[];
$logo=trim((string)($guide['logo_url']??''));
$date=(string)($guide['published_at']?:$guide['created_at']);
?>
<section class="page-hero guide-article-hero">
    <div class="container guide-hero-inner">
        <div>
            <a class="guide-back" href="/guides">← Volver a Guías</a>
            <span class="eyebrow"><?= $e((string)($guide['category']?:'GUÍA')) ?></span>
            <h1><?= $e((string)$guide['title']) ?></h1>
            <?php if(!empty($guide['summary'])): ?><p><?= $e((string)$guide['summary']) ?></p><?php endif; ?>
            <div class="guide-article-meta">
                <span>✍ Autor: <strong><?= $e((string)$guide['author']) ?></strong></span>
                <span>📅 <?= $e(date('d/m/Y H:i',strtotime($date))) ?></span>
                <span>👁 <?= number_format((int)($guide['views']??0)) ?> vistas</span>
            </div>
        </div>
        <?php if($logo!==''): ?>
        <div class="guide-hero-logo"><img src="<?= $e($logo) ?>" alt="<?= $e((string)$guide['title']) ?>"></div>
        <?php endif; ?>
    </div>
</section>
<section class="section">
    <div class="container guide-article-shell">
        <article class="guide-html-content"><?= GuideManager::sanitizeHtml((string)($guide['content_html']??'')) ?></article>
        <aside class="guide-side-card">
            <span class="guide-side-icon">📘</span>
            <h3><?= $e((string)$guide['title']) ?></h3>
            <dl>
                <div><dt>Autor</dt><dd><?= $e((string)$guide['author']) ?></dd></div>
                <div><dt>Categoría</dt><dd><?= $e((string)($guide['category']?:'General')) ?></dd></div>
                <div><dt>Publicada</dt><dd><?= $e(date('d/m/Y',strtotime($date))) ?></dd></div>
                <div><dt>Vistas</dt><dd><?= number_format((int)($guide['views']??0)) ?></dd></div>
            </dl>
            <a class="btn btn-ghost" href="/guides">Ver todas las guías</a>
        </aside>
    </div>
</section>
