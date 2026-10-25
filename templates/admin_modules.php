<?php
use Omega\Core\Security; use Omega\Core\Csrf; $e=[Security::class,'e'];
$error=$error??null; $success=$success??null;
?>
<?php if($error): ?><div class="form-alert error wide"><?= $e($error) ?></div><?php endif; ?>
<?php if($success): ?><div class="form-alert success wide"><?= $e($success) ?></div><?php endif; ?>

<div class="admin-panel module-upload-panel">
<div class="admin-panel-head"><div><span class="eyebrow">IMPORTAR</span><h2>Instalar módulo ZIP</h2></div><span class="admin-ok">module.json</span></div>
<form method="post" action="/admin/modules/install" enctype="multipart/form-data" class="module-upload-form">
<input type="hidden" name="_csrf" value="<?= $e(Csrf::token()) ?>">
<input type="file" name="module_zip" accept=".zip,application/zip" required>
<button class="btn btn-primary" type="submit">Importar e instalar</button>
</form>
<p class="module-help">Omega valida el manifiesto, la versión mínima, rutas del ZIP y registra cada archivo instalado. No ejecuta scripts de instalación arbitrarios.</p>
</div>

<div class="admin-module-cards">
<?php foreach($rows as $m): ?>
<article class="module-card">
<div class="module-card-head"><div class="module-icon">⬡</div><div><h3><?= $e($m['name']) ?></h3><span><?= $e($m['slug']) ?></span></div><b>v<?= $e($m['version']) ?></b></div>
<p><?= $e($m['description']?:'Sin descripción.') ?></p>
<div class="module-meta"><span>Autor <strong><?= $e($m['author']?:'Desconocido') ?></strong></span><span>Estado <strong class="<?= $m['is_enabled']?'ok-text':'muted-text' ?>"><?= $m['is_enabled']?'Activo':'Desactivado' ?></strong></span></div>
<div class="module-actions">
<?php if($m['installed']): ?>
<a class="btn btn-ghost" href="/admin/modules/<?= rawurlencode($m['slug']) ?>">Ver / Configurar</a>
<form method="post" action="/admin/modules/toggle"><input type="hidden" name="_csrf" value="<?= $e(Csrf::token()) ?>"><input type="hidden" name="slug" value="<?= $e($m['slug']) ?>"><input type="hidden" name="name" value="<?= $e($m['name']) ?>"><input type="hidden" name="version" value="<?= $e($m['version']) ?>"><input type="hidden" name="enabled" value="<?= $m['is_enabled']?'0':'1' ?>"><button class="btn <?= $m['is_enabled']?'btn-ghost':'btn-primary' ?>"><?= $m['is_enabled']?'Desactivar':'Activar' ?></button></form>
<?php else: ?><span class="muted-text">Detectado pero no registrado</span><?php endif; ?>
</div>
</article>
<?php endforeach; ?>
</div>
