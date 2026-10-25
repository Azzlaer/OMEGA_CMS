<?php
use Omega\Core\Security; $e=[Security::class,'e']; $news=$news??[];
?>
<section class="page-hero"><div class="container"><span class="eyebrow">COMUNIDAD</span><h1>Noticias</h1><p>Novedades, eventos y anuncios oficiales del servidor.</p></div></section>
<section class="section"><div class="container">
<?php if(!$news): ?><div class="empty-state"><span class="empty-icon">Ω</span><h3>Aún no hay noticias publicadas</h3><p>El sistema ya está preparado para recibir publicaciones desde el AdminCP.</p></div>
<?php else: ?><div class="news-list">
<?php foreach($news as $n): ?>
<article class="news-list-item">
<div class="news-list-date"><strong><?= $e(date('d',strtotime((string)($n['published_at']?:$n['created_at'])))) ?></strong><span><?= $e(date('M Y',strtotime((string)($n['published_at']?:$n['created_at'])))) ?></span></div>
<div><span class="news-category"><?= $e($n['category'] ?: 'Noticia') ?></span><h2><?= $e($n['title']) ?></h2><p><?= $e($n['summary'] ?: mb_substr(strip_tags((string)$n['content']),0,220).'…') ?></p><a class="read-more" href="/news/<?=rawurlencode((string)$n['slug'])?>">Leer noticia →</a></div>
</article>
<?php endforeach; ?></div><?php endif; ?>
</div></section>
