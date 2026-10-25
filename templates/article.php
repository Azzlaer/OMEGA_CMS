<?php use Omega\Core\Security; use Omega\Services\BbcodeRenderer; $e=[Security::class,'e']; ?>
<section class="page-hero"><div class="container"><span class="eyebrow"><?= $e($article['category'] ?: 'NOTICIA') ?></span><h1><?= $e($article['title']) ?></h1><p><?= $e($article['summary'] ?? '') ?></p></div></section>
<section class="section"><div class="container article-card">
<div class="article-meta">Publicado <?= $e(date('d/m/Y H:i',strtotime((string)($article['published_at']?:$article['created_at'])))) ?></div>
<div class="article-body bbcode-content"><?= BbcodeRenderer::render((string)$article['content']) ?></div>
</div></section>
