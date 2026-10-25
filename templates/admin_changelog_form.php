<?php
use Omega\Core\Security;
use Omega\Core\Csrf;
$e=[Security::class,'e'];
$item=$item??[];

?>
<div class="admin-panel">
<form method="post" action="/admin/changelog/save" class="omega-form admin-form changelog-admin-form">
<input type="hidden" name="_csrf" value="<?= $e(Csrf::token()) ?>">
<input type="hidden" name="id" value="<?= (int)($item['id']??0) ?>">

<label>Título
<input id="changelog-title-input" name="title" maxlength="200" required value="<?= $e($item['title']??'') ?>" placeholder="Ej: Actualización GameServer 2026.08">
</label>

<div class="form-two">
<label>Slug
<input name="slug" maxlength="180" value="<?= $e($item['slug']??'') ?>" placeholder="se genera automáticamente">
</label>
<label>Versión
<input name="version_label" maxlength="80" value="<?= $e($item['version_label']??'') ?>" placeholder="Ej: v2026.08.11">
</label>
</div>

<div class="form-two">
<label>Categoría
<select name="category">
<?php foreach(['GameServer','Cliente','Launcher','Web','Seguridad','Evento','General'] as $cat): ?>
<option value="<?= $e($cat) ?>" <?= ($item['category']??'GameServer')===$cat?'selected':'' ?>><?= $e($cat) ?></option>
<?php endforeach; ?>
</select>
</label>
<label>Estado
<select name="status">
<option value="draft" <?= ($item['status']??'draft')==='draft'?'selected':'' ?>>Borrador</option>
<option value="published" <?= ($item['status']??'')==='published'?'selected':'' ?>>Publicada</option>
</select>
</label>
</div>

<label>Resumen
<textarea name="summary" rows="3" maxlength="500" placeholder="Breve resumen de la actualización"><?= $e($item['summary']??'') ?></textarea>
</label>

<div data-bbcode-root>
<div class="bbcode-toolbar omega-bbcode-toolbar">
<button type="button" data-bbtag="b" title="Negrita"><b>B</b></button>
<button type="button" data-bbtag="i" title="Cursiva"><i>I</i></button>
<button type="button" data-bbtag="u" title="Subrayado"><u>U</u></button>
<button type="button" data-bbtag="s" title="Tachado"><s>S</s></button>
<span class="bbcode-sep"></span>
<button type="button" data-bbtag="left" title="Alinear izquierda">IZQ</button>
<button type="button" data-bbtag="center" title="Centrar">CENTRO</button>
<button type="button" data-bbtag="right" title="Alinear derecha">DER</button>
<span class="bbcode-sep"></span>
<button type="button" data-bbtag="h2" title="Título grande">H2</button>
<button type="button" data-bbtag="h3" title="Subtítulo">H3</button>
<button type="button" data-bbtag="color" data-bbvalue="#d5a33d" title="Color">COLOR</button>
<button type="button" data-bbtag="size" data-bbvalue="18" title="Tamaño">SIZE</button>
<span class="bbcode-sep"></span>
<button type="button" data-bbtag="url" data-bbvalue="https://" title="Enlace">🔗 LINK</button>
<button type="button" data-bbtag="youtube" title="Video YouTube">▶ YOUTUBE</button>
<button type="button" data-bbtag="img" title="Imagen HTTPS">IMG</button>
<span class="bbcode-sep"></span>
<button type="button" data-bbtag="quote" title="Cita">QUOTE</button>
<button type="button" data-bbtag="code" title="Código">CODE</button>
<button type="button" data-bbtag="list" title="Lista">LISTA</button>
<button type="button" data-bbtag="item" title="Elemento de lista">[*]</button>
<button type="button" data-bbtag="hr" title="Separador">─ HR</button>
</div>
<div class="bbcode-js-status" data-bbcode-status>Editor BBCode cargando…</div>

<label>Contenido / BBCode
<textarea id="changelog-content" data-bbcode-editor name="content" rows="16" required placeholder="[b]Cambios GameServer[/b]
[*] Nuevo sistema
[*] Corrección de errores
[color=#d5a33d]Mejora importante[/color]"><?= $e($item['content']??'') ?></textarea>
<small class="bbcode-help">BBCode: [b], [i], [u], [s], [left], [center], [right], [h2], [h3], [color], [size], [url], [youtube], [img], [quote], [code], [list], [*], [hr].</small>
<div class="bbcode-media-preview-wrap">
    <div class="bbcode-media-preview-title">Vista previa multimedia</div>
    <div class="bbcode-media-preview" data-bbcode-media-preview>
        <div class="bbcode-preview-empty">No hay videos o imágenes detectados todavía.</div>
    </div>
    <small class="bbcode-media-help">
        Puedes pegar una URL de YouTube sola en una línea y Omega la convertirá automáticamente en video al publicar.
        También detecta URLs HTTPS directas a PNG, JPG, GIF, WEBP y AVIF.
    </small>
</div>
</label>
</div>

<div class="changelog-style-box">
<div class="admin-panel-head"><div><span class="eyebrow">ESTILO</span><h2>Diseño del título</h2></div></div>
<div class="changelog-style-grid">
<label>Color título<input type="color" name="title_color" value="<?= $e($item['title_color']??'#f4f0e8') ?>"></label>
<label>Color borde<input type="color" name="title_border_color" value="<?= $e($item['title_border_color']??'#d5a33d') ?>"></label>
<label>Fondo título<input type="color" name="title_bg_color" value="<?= $e($item['title_bg_color']??'#121820') ?>"></label>
<label>Color sombra<input type="color" name="title_shadow_color" value="<?= $e($item['title_shadow_color']??'#000000') ?>"></label>
<label>Grosor borde
<input type="range" name="title_border_width" min="0" max="6" value="<?= (int)($item['title_border_width']??0) ?>">
</label>
<label>Tamaño sombra
<input type="range" name="title_shadow_size" min="0" max="20" value="<?= (int)($item['title_shadow_size']??0) ?>">
</label>
</div>

<div class="changelog-preview-wrap">
<span>Vista previa</span>
<div id="changelog-title-preview" class="changelog-title-preview"><?= $e($item['title']??'Título de actualización') ?></div>
</div>
</div>

<label class="check-line">
<input type="checkbox" name="featured" value="1" <?= !empty($item['featured'])?'checked':'' ?>>
<span>Destacar esta actualización</span>
</label>

<div class="admin-form-actions">
<a class="btn btn-ghost" href="/admin/changelog">Cancelar</a>
<button class="btn btn-primary" type="submit">Guardar actualización</button>
</div>
</form>
</div>

<script src="/public/assets/js/bbcode-editor.js?v=171" defer></script>