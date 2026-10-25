<?php
$moduleHtml=$moduleHtml??'';
?>
<section class="page-hero compact">
<div class="container">
    <a class="back-link" href="/rankings?type=guilds">← Rankings de Clanes</a>
    <span class="eyebrow">PUBLIC RANKING</span>
    <h1><?= htmlspecialchars($title??'Clanes',ENT_QUOTES,'UTF-8') ?></h1>
    <p>Directorio público del servidor · no requiere iniciar sesión.</p>
</div>
</section>

<section class="section">
<div class="container">
    <?= $moduleHtml ?>
</div>
</section>
