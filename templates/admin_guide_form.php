<?php
use Omega\Core\Security;
use Omega\Core\Csrf;
$e=[Security::class,'e'];
$item=$item??[];
$isEdit=!empty($item['id']);
$logo=trim((string)($item['logo_url']??''));
?>
<div class="admin-panel">
<form method="post" action="/admin/guides/save" enctype="multipart/form-data" class="omega-form admin-form" data-guide-editor>
<input type="hidden" name="_csrf" value="<?= $e(Csrf::token()) ?>">
<input type="hidden" name="id" value="<?= (int)($item['id']??0) ?>">

<label>Nombre de la guía<input name="title" maxlength="180" required value="<?= $e((string)($item['title']??'')) ?>" placeholder="Ej: Guía completa de Blood Castle"></label>
<div class="form-two">
    <label>Slug / URL<input name="slug" maxlength="160" value="<?= $e((string)($item['slug']??'')) ?>" placeholder="Se genera automáticamente"></label>
    <label>Categoría<input name="category" maxlength="60" value="<?= $e((string)($item['category']??'General')) ?>" placeholder="Eventos, Personajes, Items..."></label>
</div>
<label>Resumen<textarea name="summary" rows="3" maxlength="700" placeholder="Descripción breve que aparecerá en el listado de guías."><?= $e((string)($item['summary']??'')) ?></textarea></label>

<div class="guide-logo-admin-grid">
    <div>
        <label>Logo desde tu PC<input type="file" name="logo_file" accept="image/jpeg,image/png,image/gif,image/webp"></label>
        <small>JPG, PNG, GIF o WEBP. Máximo 4 MB. Si subes una imagen, tendrá prioridad sobre la URL remota.</small>
    </div>
    <div>
        <label>O usar logo por URL<input type="url" name="logo_remote" maxlength="700" placeholder="https://servidor.com/imagen.webp"></label>
        <small>La imagen remota no se copia al servidor; se utilizará directamente desde esa dirección.</small>
    </div>
    <?php if($logo!==''): ?>
    <div class="guide-current-logo">
        <span>Logo actual</span><img src="<?= $e($logo) ?>" alt="">
        <label class="check-line"><input type="checkbox" name="remove_logo" value="1"> <span>Eliminar logo actual</span></label>
    </div>
    <?php endif; ?>
</div>

<div class="guide-html-editor-wrap">
    <div class="guide-editor-heading">
        <div><strong>Contenido HTML</strong><small>HTML permitido y sanitizado automáticamente al guardar.</small></div>
        <a href="#html-help" class="guide-help-link">Ver etiquetas permitidas</a>
    </div>
    <div class="html-guide-toolbar" data-html-toolbar>
        <button type="button" data-open="&lt;h2&gt;" data-close="&lt;/h2&gt;">H2</button>
        <button type="button" data-open="&lt;h3&gt;" data-close="&lt;/h3&gt;">H3</button>
        <button type="button" data-open="&lt;strong&gt;" data-close="&lt;/strong&gt;"><b>B</b></button>
        <button type="button" data-open="&lt;em&gt;" data-close="&lt;/em&gt;"><i>I</i></button>
        <button type="button" data-open="&lt;p&gt;" data-close="&lt;/p&gt;">P</button>
        <button type="button" data-open="&lt;blockquote&gt;" data-close="&lt;/blockquote&gt;">❝ Cita</button>
        <button type="button" data-open="&lt;ul&gt;
&lt;li&gt;" data-close="&lt;/li&gt;
&lt;/ul&gt;">• Lista</button>
        <button type="button" data-template="&lt;a href=&quot;https://&quot; target=&quot;_blank&quot;&gt;Texto del enlace&lt;/a&gt;">🔗 Link</button>
        <button type="button" data-template="&lt;img src=&quot;https://&quot; alt=&quot;Descripción&quot;&gt;">🖼 Imagen</button>
        <button type="button" data-open="&lt;pre&gt;&lt;code&gt;" data-close="&lt;/code&gt;&lt;/pre&gt;">&lt;/&gt; Código</button>
        <button type="button" data-template="&lt;hr&gt;">─ HR</button>
        <button type="button" data-template="&lt;iframe src=&quot;https://www.youtube.com/embed/VIDEO_ID&quot; width=&quot;800&quot; height=&quot;450&quot; title=&quot;Video&quot; allowfullscreen&gt;&lt;/iframe&gt;">▶ YouTube</button>
    </div>
    <textarea id="guideHtmlEditor" name="content_html" rows="24" required spellcheck="false" placeholder="<h2>Introducción</h2>\n<p>Escribe aquí tu guía usando HTML...</p>"><?= $e((string)($item['content_html']??'')) ?></textarea>
</div>

<div id="html-help" class="guide-html-help">
    <strong>HTML admitido</strong>
    <code>&lt;p&gt; &lt;br&gt; &lt;h2&gt; &lt;h3&gt; &lt;h4&gt; &lt;strong&gt; &lt;b&gt; &lt;em&gt; &lt;i&gt; &lt;u&gt; &lt;s&gt; &lt;blockquote&gt; &lt;ul&gt; &lt;ol&gt; &lt;li&gt; &lt;a&gt; &lt;img&gt; &lt;figure&gt; &lt;figcaption&gt; &lt;table&gt; &lt;thead&gt; &lt;tbody&gt; &lt;tr&gt; &lt;th&gt; &lt;td&gt; &lt;hr&gt; &lt;code&gt; &lt;pre&gt; &lt;span&gt; &lt;div&gt;</code>
    <p>También se permiten iframes únicamente para videos HTTPS de YouTube. Scripts, formularios, eventos JavaScript y URLs peligrosas se eliminan automáticamente.</p>
</div>

<div class="form-two">
<label>Estado<select name="status"><option value="draft" <?= ($item['status']??'draft')==='draft'?'selected':'' ?>>Borrador</option><option value="published" <?= ($item['status']??'')==='published'?'selected':'' ?>>Publicada</option></select></label>
<label class="check-line"><input type="checkbox" name="featured" value="1" <?= !empty($item['featured'])?'checked':'' ?>> <span>Guía destacada (aparecerá primero)</span></label>
</div>
<div class="admin-form-actions"><a class="btn btn-ghost" href="/admin/guides">Cancelar</a><?php if($isEdit && ($item['status']??'')==='published'): ?><a class="btn btn-ghost" target="_blank" rel="noopener" href="/guides/<?= $e(rawurlencode((string)$item['slug'])) ?>">↗ Ver publicada</a><?php endif; ?><button class="btn btn-primary" type="submit">Guardar guía</button></div>
</form>
</div>

<script src="/public/assets/js/guide-html-editor.js?v=100" defer></script>
