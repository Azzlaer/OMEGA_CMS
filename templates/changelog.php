<?php
use Omega\Core\Security;
$e=[Security::class,'e'];
$rows=$rows??[];
?>
<section class="page-hero changelog-hero">
<div class="container">
<span class="eyebrow">HISTORIAL DEL SERVIDOR</span>
<h1>Changelog</h1>
<p>Actualizaciones de GameServer, Cliente, Launcher, Web y sistemas del servidor.</p>
</div>
</section>

<section class="section">
<div class="container changelog-list">
<?php foreach($rows as $row):
$style='color:'.$row['title_color'].';background:'.$row['title_bg_color'].';border:'.(int)$row['title_border_width'].'px solid '.$row['title_border_color'].';';
if((int)$row['title_shadow_size']>0){
    $style.='text-shadow:0 0 '.(int)$row['title_shadow_size'].'px '.$row['title_shadow_color'].';';
}
?>
<article class="changelog-card <?= !empty($row['featured'])?'featured':'' ?>">
<div class="changelog-meta">
<span class="changelog-category cat-<?= strtolower($e($row['category'])) ?>"><?= $e($row['category']) ?></span>
<?php if(!empty($row['version_label'])): ?><span class="changelog-version"><?= $e($row['version_label']) ?></span><?php endif; ?>
<span class="changelog-date"><?= $e(date('d/m/Y',strtotime((string)($row['published_at']?:$row['created_at'])))) ?></span>
</div>
<a href="/changelog/<?= rawurlencode((string)$row['slug']) ?>" class="changelog-title-link">
<h2 class="changelog-styled-title" style="<?= $e($style) ?>"><?= $e($row['title']) ?></h2>
</a>
<?php if(!empty($row['summary'])): ?><p><?= $e($row['summary']) ?></p><?php endif; ?>
<a class="read-more" href="/changelog/<?= rawurlencode((string)$row['slug']) ?>">Ver cambios completos →</a>
</article>
<?php endforeach; ?>

<?php if(!$rows): ?>
<div class="empty-state">
<span class="empty-icon">↻</span>
<h3>Todavía no hay actualizaciones publicadas</h3>
<p>Las publicaciones creadas desde el AdminCP aparecerán aquí.</p>
</div>
<?php endif; ?>
</div>
</section>
