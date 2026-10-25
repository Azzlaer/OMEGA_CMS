<?php use Omega\Core\Security; $e=[Security::class,'e']; ?>
<section class="page-hero">
<div class="container"><span class="eyebrow"><?= $e($eyebrow ?? 'OMEGA MU') ?></span><h1><?= $e($heading ?? $title ?? '') ?></h1><p><?= $e($lead ?? '') ?></p></div>
</section>
<section class="section">
<div class="container content-card">
<?= $content ?? '' ?>
</div>
</section>
