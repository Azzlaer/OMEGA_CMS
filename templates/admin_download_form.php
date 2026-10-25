<?php
use Omega\Core\Security; use Omega\Core\Csrf; $e=[Security::class,'e']; $item=$item??[];
?>
<div class="admin-panel"><form method="post" action="/admin/downloads/save" class="omega-form admin-form">
<input type="hidden" name="_csrf" value="<?= $e(Csrf::token()) ?>"><input type="hidden" name="id" value="<?= (int)($item['id']??0) ?>">
<label>Título<input name="title" maxlength="120" required value="<?= $e($item['title']??'') ?>"></label>
<label>Descripción<textarea name="description" rows="4"><?= $e($item['description']??'') ?></textarea></label>
<label>URL<input type="url" name="url" required value="<?= $e($item['url']??'') ?>"></label>
<div class="form-two"><label>Texto botón<input name="button_label" maxlength="50" value="<?= $e($item['button_label']??'Descargar') ?>"></label><label>Categoría<input name="category" maxlength="50" value="<?= $e($item['category']??'Cliente') ?>"></label></div>
<div class="form-two"><label>Orden<input type="number" name="sort_order" value="<?= (int)($item['sort_order']??0) ?>"></label><label class="check-line"><input type="checkbox" name="is_active" value="1" <?= !isset($item['is_active'])||!empty($item['is_active'])?'checked':'' ?>> <span>Visible públicamente</span></label></div>
<div class="admin-form-actions"><a class="btn btn-ghost" href="/admin/downloads">Cancelar</a><button class="btn btn-primary" type="submit">Guardar descarga</button></div>
</form></div>
