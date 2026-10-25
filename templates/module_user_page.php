<?php
$moduleHtml=$moduleHtml??'';
?>
<section class="page-hero compact">
<div class="container">
<a class="back-link" href="/account">← Volver a mi cuenta</a>
<span class="eyebrow">ACCOUNT MODULE</span>
<h1><?= htmlspecialchars($title??'Módulo',ENT_QUOTES,'UTF-8') ?></h1>
</div>
</section>
<section class="section">
<div class="container">
<?= $moduleHtml ?>
</div>
</section>
