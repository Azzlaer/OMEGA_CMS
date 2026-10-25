<?php
use Omega\Core\Security; use Omega\Core\Csrf; $e=[Security::class,'e'];
$m=$module; $schema=$m['manifest']['config_schema']??[]; $customAdminHtml=$customAdminHtml??'';
?>
<div class="module-detail-head">
<div><a class="back-link" href="/admin/modules">← Todos los módulos</a><span class="eyebrow">MÓDULO</span><h2><?= $e($m['name']) ?></h2><p><?= $e($m['description']??'') ?></p></div>
<div class="module-version-badge">v<?= $e($m['version']) ?></div>
</div>

<div class="admin-grid">
<section class="admin-panel">
<div class="admin-panel-head"><div><span class="eyebrow">INFORMACIÓN</span><h2>Paquete instalado</h2></div></div>
<div class="admin-status-list">
<div><span>Slug</span><strong><?= $e($m['slug']) ?></strong></div>
<div><span>Versión</span><strong><?= $e($m['version']) ?></strong></div>
<div><span>Autor</span><strong><?= $e($m['author']??'') ?></strong></div>
<div><span>Core mínimo</span><strong><?= $e($m['manifest']['minimum_core']??'0.1.0') ?></strong></div>
<div><span>Archivos registrados</span><strong><?= count($files) ?></strong></div>
</div>
</section>
<section class="admin-panel">
<div class="admin-panel-head"><div><span class="eyebrow">ESTADO</span><h2>Control del módulo</h2></div></div>
<form method="post" action="/admin/modules/toggle">
<input type="hidden" name="_csrf" value="<?= $e(Csrf::token()) ?>"><input type="hidden" name="slug" value="<?= $e($m['slug']) ?>"><input type="hidden" name="name" value="<?= $e($m['name']) ?>"><input type="hidden" name="version" value="<?= $e($m['version']) ?>"><input type="hidden" name="enabled" value="<?= $m['is_enabled']?'0':'1' ?>">
<button class="btn <?= $m['is_enabled']?'btn-ghost':'btn-primary' ?> full"><?= $m['is_enabled']?'Desactivar módulo':'Activar módulo' ?></button>
</form>
<form method="post" action="/admin/modules/uninstall" onsubmit="return confirm('¿Desinstalar el módulo y borrar sus archivos?')">
<input type="hidden" name="_csrf" value="<?= $e(Csrf::token()) ?>"><input type="hidden" name="slug" value="<?= $e($m['slug']) ?>"><button class="btn btn-danger full" type="submit">Desinstalar módulo</button>
</form>
</section>
</div>

<?php if($schema): ?>
<section class="admin-panel module-config-panel">
<div class="admin-panel-head"><div><span class="eyebrow">CONFIGURACIÓN</span><h2><?= $e($m['name']) ?></h2></div></div>
<form method="post" action="/admin/modules/config/save" class="omega-form admin-form">
<input type="hidden" name="_csrf" value="<?= $e(Csrf::token()) ?>"><input type="hidden" name="slug" value="<?= $e($m['slug']) ?>">
<div class="module-config-grid">
<?php foreach($schema as $field):
$key=(string)($field['key']??''); if($key==='')continue; $type=(string)($field['type']??'text'); $val=$config[$key]??($field['default']??null); ?>
<label><?= $e($field['label']??$key) ?>
<?php if($type==='boolean'): ?>
<span class="toggle-field"><input type="checkbox" name="<?= $e($key) ?>" value="1" <?= !empty($val)?'checked':'' ?>><span><?= $e($field['help']??'Activar opción') ?></span></span>
<?php elseif($type==='integer'): ?>
<input type="number" name="<?= $e($key) ?>" value="<?= (int)$val ?>" <?= isset($field['min'])?'min="'.(int)$field['min'].'"':'' ?> <?= isset($field['max'])?'max="'.(int)$field['max'].'"':'' ?>>
<?php elseif($type==='select'): ?>
<select name="<?= $e($key) ?>"><?php foreach((array)($field['options']??[]) as $ov=>$ol): ?><option value="<?= $e((string)$ov) ?>" <?= (string)$val===(string)$ov?'selected':'' ?>><?= $e((string)$ol) ?></option><?php endforeach; ?></select>
<?php elseif($type==='secret'): ?>
<input type="password" name="<?= $e($key) ?>" value="" autocomplete="new-password" placeholder="<?= $val!==''?'Configurado · dejar vacío para conservar':'Ingresar secreto' ?>">
<?php else: ?>
<input name="<?= $e($key) ?>" value="<?= $e((string)$val) ?>">
<?php endif; ?>
<?php if(!empty($field['help']) && $type!=='boolean'): ?><small><?= $e($field['help']) ?></small><?php endif; ?>
</label>
<?php endforeach; ?>
</div>
<div class="admin-form-actions"><button class="btn btn-primary" type="submit">Guardar configuración</button></div>
</form>
</section>
<?php endif; ?>

<?php $actions=$m['manifest']['actions']??[]; if(is_array($actions)&&$actions): ?>
<section class="admin-panel module-actions-panel">
<div class="admin-panel-head"><div><span class="eyebrow">ACCIONES</span><h2>Herramientas del módulo</h2></div></div>
<div class="module-admin-actions">
<?php foreach($actions as $act): if(empty($act['key']))continue; ?>
<form method="post" action="/admin/modules/action">
<input type="hidden" name="_csrf" value="<?= $e(Csrf::token()) ?>">
<input type="hidden" name="slug" value="<?= $e($m['slug']) ?>">
<input type="hidden" name="module_action" value="<?= $e($act['key']) ?>">
<button class="btn btn-primary" type="submit"><?= $e($act['label']??$act['key']) ?></button>
<?php if(!empty($act['help'])): ?><small><?= $e($act['help']) ?></small><?php endif; ?>
</form>
<?php endforeach; ?>
</div>
</section>
<?php endif; ?>

<?php if($customAdminHtml!==''): ?>
<section class="module-custom-admin">
<?= $customAdminHtml ?>
</section>
<?php endif; ?>

<section class="admin-panel">
<div class="admin-panel-head"><div><span class="eyebrow">ARCHIVOS</span><h2>Contenido instalado</h2></div></div>
<div class="module-files"><?php foreach($files as $f): ?><div><code><?= $e($f['relative_path']) ?></code><small><?= $e(substr((string)$f['sha256'],0,16)) ?>…</small></div><?php endforeach; ?></div>
</section>
