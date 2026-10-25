<?php
use Omega\Core\Security;
$e=[Security::class,'e'];
$guides=$guides??[];
$categories=$categories??[];
$q=(string)($q??'');
$selectedCategory=(string)($selectedCategory??'');
?>
<section class="page-hero guides-hero">
    <div class="container">
        <span class="eyebrow">BIBLIOTECA DEL SERVIDOR</span>
        <h1>Guías</h1>
        <p>Aprende sistemas, eventos, progresión, equipamiento y secretos del servidor con tutoriales creados por nuestro staff.</p>
    </div>
</section>

<section class="section guides-section">
<div class="container">
    <form class="guides-filter" method="get" action="/guides">
        <label class="guides-search">
            <span>Buscar guía</span>
            <input type="search" name="q" value="<?= $e($q) ?>" placeholder="Ej: Blood Castle, alas, joyas...">
        </label>
        <label>
            <span>Categoría</span>
            <select name="category">
                <option value="">Todas</option>
                <?php foreach($categories as $cat): $name=(string)($cat['category']??''); ?>
                    <option value="<?= $e($name) ?>" <?= $selectedCategory===$name?'selected':'' ?>><?= $e($name) ?> (<?= (int)($cat['total']??0) ?>)</option>
                <?php endforeach; ?>
            </select>
        </label>
        <button class="btn btn-primary" type="submit">Buscar</button>
        <?php if($q!=='' || $selectedCategory!==''): ?><a class="btn btn-ghost" href="/guides">Limpiar</a><?php endif; ?>
    </form>

    <?php if($guides): ?>
    <div class="guide-grid">
        <?php foreach($guides as $guide):
            $date=(string)($guide['published_at']?:$guide['created_at']);
            $logo=trim((string)($guide['logo_url']??''));
        ?>
        <article class="guide-card <?= !empty($guide['featured'])?'is-featured':'' ?>">
            <a class="guide-card-cover" href="/guides/<?= $e(rawurlencode((string)$guide['slug'])) ?>">
                <?php if($logo!==''): ?>
                    <img src="<?= $e($logo) ?>" alt="<?= $e((string)$guide['title']) ?>" loading="lazy">
                <?php else: ?>
                    <div class="guide-card-placeholder"><span>Ω</span><small>OMEGA GUIDE</small></div>
                <?php endif; ?>
                <?php if(!empty($guide['featured'])): ?><span class="guide-featured-badge">★ Destacada</span><?php endif; ?>
            </a>
            <div class="guide-card-body">
                <div class="guide-card-kicker"><?= $e((string)($guide['category']?:'General')) ?></div>
                <h2><a href="/guides/<?= $e(rawurlencode((string)$guide['slug'])) ?>"><?= $e((string)$guide['title']) ?></a></h2>
                <?php if(!empty($guide['summary'])): ?><p><?= $e((string)$guide['summary']) ?></p><?php endif; ?>
                <div class="guide-card-meta">
                    <span>✍ <?= $e((string)$guide['author']) ?></span>
                    <span>📅 <?= $e(date('d/m/Y',strtotime($date))) ?></span>
                    <span>👁 <?= number_format((int)($guide['views']??0)) ?></span>
                </div>
                <a class="guide-read-more" href="/guides/<?= $e(rawurlencode((string)$guide['slug'])) ?>">Leer guía <span>→</span></a>
            </div>
        </article>
        <?php endforeach; ?>
    </div>
    <?php else: ?>
        <div class="guides-empty">
            <span>📚</span>
            <h2>No encontramos guías</h2>
            <p><?= ($q!=='' || $selectedCategory!=='')?'Prueba con otros términos o limpia los filtros.':'Todavía no se han publicado guías del servidor.' ?></p>
        </div>
    <?php endif; ?>
</div>
</section>
