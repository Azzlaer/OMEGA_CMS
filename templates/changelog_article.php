<?php
use Omega\Core\Security;
use Omega\Services\ChangelogManager;
$e=[Security::class,'e'];
$row=$article;

$style='color:'.$row['title_color'].';background:'.$row['title_bg_color'].';border:'.(int)$row['title_border_width'].'px solid '.$row['title_border_color'].';';
if((int)$row['title_shadow_size']>0){
    $style.='text-shadow:0 0 '.(int)$row['title_shadow_size'].'px '.$row['title_shadow_color'].';';
}
?>
<section class="page-hero compact changelog-article-hero">
<div class="container">
<a class="back-link" href="/changelog">← Volver al changelog</a>
<div class="changelog-meta">
<span class="changelog-category"><?= $e($row['category']) ?></span>
<?php if(!empty($row['version_label'])): ?><span class="changelog-version"><?= $e($row['version_label']) ?></span><?php endif; ?>
</div>
<h1 class="changelog-styled-title article-title" style="<?= $e($style) ?>"><?= $e($row['title']) ?></h1>
<?php if(!empty($row['summary'])): ?><p><?= $e($row['summary']) ?></p><?php endif; ?>
</div>
</section>

<section class="section">
<div class="container article-card changelog-article">
<div class="article-meta">
Publicado <?= $e(date('d/m/Y H:i',strtotime((string)($row['published_at']?:$row['created_at'])))) ?>
</div>
<div class="changelog-content bbcode-content"><?= ChangelogManager::renderBbcode((string)$row['content']) ?></div>
</div>
</section>
