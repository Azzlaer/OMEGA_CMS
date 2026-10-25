<?php
use Omega\Core\Security;
$e=[Security::class,'e'];
$downloads=$downloads??[];
?>
<section class="page-hero"><div class="container"><span class="eyebrow">JUGAR AHORA</span><h1>Descargas</h1><p>Cliente, parches y herramientas oficiales del servidor.</p></div></section>
<section class="section"><div class="container">
<?php if(!$downloads): ?>
<div class="empty-state"><span class="empty-icon">⇩</span><h3>No hay descargas publicadas</h3><p>El administrador todavía no configuró enlaces de descarga.</p></div>
<?php else: ?>
<div class="download-grid">
<?php foreach($downloads as $d): ?>
<div class="download-card">
    <span><?= $e(str_pad((string)$d['id'],2,'0',STR_PAD_LEFT)) ?></span>
    <small class="news-category"><?= $e($d['category']??'Descarga') ?></small>
    <h3><?= $e($d['title']) ?></h3>
    <p><?= $e($d['description']??'') ?></p>
    <a class="btn btn-primary" target="_blank" rel="noopener noreferrer" href="<?= $e($d['url']) ?>"><?= $e($d['button_label']??'Descargar') ?></a>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>
</div></section>
