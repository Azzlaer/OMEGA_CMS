<?php
use Omega\Core\Security; use Omega\Core\Csrf;
$e=[Security::class,'e']; $item=$item??[];

?>
<div class="admin-panel">
<form method="post" action="/admin/news/save" class="omega-form admin-form">
<input type="hidden" name="_csrf" value="<?= $e(Csrf::token()) ?>"><input type="hidden" name="id" value="<?= (int)($item['id']??0) ?>">
<label>Título<input name="title" maxlength="180" required value="<?= $e($item['title']??'') ?>"></label>
<div class="form-two"><label>Slug<input name="slug" maxlength="160" value="<?= $e($item['slug']??'') ?>"></label><label>Categoría<input name="category" maxlength="50" value="<?= $e($item['category']??'Noticias') ?>"></label></div>
<label>Resumen<textarea name="summary" rows="3"><?= $e($item['summary']??'') ?></textarea></label>
<div data-bbcode-root>
<label>Contenido / BBCode</label>
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
<textarea data-bbcode-editor name="content" rows="16" required placeholder="[h2]Título[/h2]
[center][b]Texto centrado[/b][/center]
[youtube]https://www.youtube.com/watch?v=VIDEO_ID[/youtube]"><?= $e($item['content']??'') ?></textarea>
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
</div>
<div class="form-two">
<label>Estado<select name="status"><option value="draft" <?= ($item['status']??'')==='draft'?'selected':'' ?>>Borrador</option><option value="published" <?= ($item['status']??'')==='published'?'selected':'' ?>>Publicada</option></select></label>
<label class="check-line"><input type="checkbox" name="featured" value="1" <?= !empty($item['featured'])?'checked':'' ?>> <span>Noticia destacada</span></label>
</div>
<div class="admin-form-actions"><a class="btn btn-ghost" href="/admin/news">Cancelar</a><button class="btn btn-primary" type="submit">Guardar noticia</button></div>
</form>
</div>

<script src="/public/assets/js/bbcode-editor.js?v=171" defer></script>