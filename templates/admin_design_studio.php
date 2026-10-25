<?php
use Omega\Core\Security;
use Omega\Core\Csrf;

$e=[Security::class,'e'];
$settings=$settings??[];
$sections=$sections??[];
$assets=$assets??[];
$presets=$presets??[];
$error=$error??null;
$success=$success??null;

$imageAssets=array_values(array_filter($assets,static fn($a)=>($a['media_type']??'')==='image'));
$videoAssets=array_values(array_filter($assets,static fn($a)=>($a['media_type']??'')==='video'));

function dsBytes(int $bytes): string {
    if($bytes>=1024*1024) return number_format($bytes/(1024*1024),1,',','.').' MB';
    if($bytes>=1024) return number_format($bytes/1024,0,',','.').' KB';
    return $bytes.' B';
}
?>
<?php if($success): ?><div class="form-alert success wide"><?= $e($success) ?></div><?php endif; ?>
<?php if($error): ?><div class="form-alert error wide"><?= $e($error) ?></div><?php endif; ?>

<div class="design-admin-hero">
    <div>
        <span class="eyebrow">OMEGA VISUAL ENGINE</span>
        <h2>🎨 Visual Design Studio</h2>
        <p>Personaliza fondos, video del header, colores, multimedia y secciones sin editar PHP/CSS.</p>
    </div>
    <a class="btn btn-primary" href="/" target="_blank" rel="noopener">👁 Ver sitio público</a>
</div>

<div class="design-admin-tabs">
    <a href="#appearance">🖌 Apariencia</a>
    <a href="#hero">🎬 Header / Hero</a>
    <a href="#sections">🖼 Fondos por Sección</a>
    <a href="#library">📁 Biblioteca</a>
    <a href="#presets">💾 Presets</a>
</div>

<section class="admin-panel" id="appearance">
    <div class="admin-panel-head">
        <div><span class="eyebrow">GLOBAL THEME</span><h2>🖌 Apariencia Global</h2></div>
    </div>

    <form method="post" action="/admin/design-studio/settings" class="omega-form">
        <input type="hidden" name="_csrf" value="<?= $e(Csrf::token()) ?>">

        <div class="design-form-grid colors">
            <?php
            $colors=[
                'theme_bg'=>'Fondo',
                'theme_panel'=>'Panel',
                'theme_text'=>'Texto',
                'theme_border'=>'Bordes',
                'theme_accent'=>'Dorado / Accent',
                'theme_accent2'=>'Accent secundario',
            ];
            foreach($colors as $key=>$label):
            ?>
            <label><?= $e($label) ?>
                <div class="design-color-row">
                    <input type="color" value="<?= $e((string)($settings[$key]??'#000000')) ?>"
                           oninput="this.nextElementSibling.value=this.value">
                    <input name="<?= $e($key) ?>" value="<?= $e((string)($settings[$key]??'')) ?>"
                           maxlength="7" pattern="#[0-9A-Fa-f]{6}" required>
                </div>
            </label>
            <?php endforeach; ?>
        </div>

        <div class="design-form-grid">
            <label>Radio de bordes
                <input type="number" min="0" max="30" name="theme_radius"
                       value="<?= (int)($settings['theme_radius']??10) ?>">
            </label>
            <label>Oscurecimiento global del fondo (%)
                <input type="number" min="0" max="90" name="page_overlay"
                       value="<?= (int)($settings['page_overlay']??46) ?>">
            </label>
            <label>Blur del fondo (px)
                <input type="number" min="0" max="12" name="background_blur"
                       value="<?= (int)($settings['background_blur']??0) ?>">
            </label>
            <label class="check-line">
                <input type="checkbox" name="background_fixed" value="1"
                    <?= !empty($settings['background_fixed'])?'checked':'' ?>>
                Fondo fijo mientras se hace scroll
            </label>
        </div>

        <input type="hidden" name="hero_enabled" value="<?= $e((string)($settings['hero_enabled']??'0')) ?>">
        <input type="hidden" name="hero_mode" value="<?= $e((string)($settings['hero_mode']??'none')) ?>">
        <input type="hidden" name="hero_asset_id" value="<?= (int)($settings['hero_asset_id']??0) ?>">
        <input type="hidden" name="hero_overlay" value="<?= (int)($settings['hero_overlay']??52) ?>">
        <input type="hidden" name="hero_height" value="<?= (int)($settings['hero_height']??300) ?>">
        <input type="hidden" name="hero_position" value="<?= $e((string)($settings['hero_position']??'center center')) ?>">
        <input type="hidden" name="hero_blur" value="<?= (int)($settings['hero_blur']??0) ?>">

        <button class="btn btn-primary" type="submit">💾 Guardar apariencia</button>
    </form>
</section>

<section class="admin-panel" id="hero">
    <div class="admin-panel-head">
        <div><span class="eyebrow">CINEMATIC HEADER</span><h2>🎬 Header / Hero</h2></div>
    </div>

    <form method="post" action="/admin/design-studio/settings" class="omega-form">
        <input type="hidden" name="_csrf" value="<?= $e(Csrf::token()) ?>">

        <?php foreach(['theme_bg','theme_panel','theme_text','theme_border','theme_accent','theme_accent2','theme_radius','page_overlay','background_blur','background_fixed'] as $k): ?>
            <input type="hidden" name="<?= $e($k) ?>" value="<?= $e((string)($settings[$k]??'')) ?>">
        <?php endforeach; ?>

        <div class="design-form-grid">
            <label class="check-line">
                <input type="checkbox" name="hero_enabled" value="1"
                    <?= !empty($settings['hero_enabled'])?'checked':'' ?>>
                Activar Hero multimedia
            </label>

            <label>Tipo de Hero
                <select name="hero_mode">
                    <option value="none" <?= ($settings['hero_mode']??'')==='none'?'selected':'' ?>>Sin media</option>
                    <option value="image" <?= ($settings['hero_mode']??'')==='image'?'selected':'' ?>>Imagen</option>
                    <option value="video" <?= ($settings['hero_mode']??'')==='video'?'selected':'' ?>>Video MP4 / WEBM</option>
                </select>
            </label>

            <label>Media del Hero
                <select name="hero_asset_id">
                    <option value="0">— Seleccionar desde Biblioteca —</option>
                    <optgroup label="Imágenes">
                    <?php foreach($imageAssets as $a): ?>
                        <option value="<?= (int)$a['id'] ?>" <?= (int)($settings['hero_asset_id']??0)===(int)$a['id']?'selected':'' ?>>
                            <?= $e((string)$a['label']) ?>
                        </option>
                    <?php endforeach; ?>
                    </optgroup>
                    <optgroup label="Videos">
                    <?php foreach($videoAssets as $a): ?>
                        <option value="<?= (int)$a['id'] ?>" <?= (int)($settings['hero_asset_id']??0)===(int)$a['id']?'selected':'' ?>>
                            <?= $e((string)$a['label']) ?>
                        </option>
                    <?php endforeach; ?>
                    </optgroup>
                </select>
            </label>

            <label>Altura del Header (px)
                <input type="number" name="hero_height" min="180" max="760"
                       value="<?= (int)($settings['hero_height']??300) ?>">
            </label>

            <label>Overlay oscuro (%)
                <input type="number" name="hero_overlay" min="0" max="90"
                       value="<?= (int)($settings['hero_overlay']??52) ?>">
            </label>

            <label>Blur (px)
                <input type="number" name="hero_blur" min="0" max="12"
                       value="<?= (int)($settings['hero_blur']??0) ?>">
            </label>

            <label>Posición
                <select name="hero_position">
                    <?php foreach(['center center'=>'Centro','center top'=>'Arriba','center bottom'=>'Abajo','left center'=>'Izquierda','right center'=>'Derecha'] as $value=>$label): ?>
                    <option value="<?= $e($value) ?>" <?= ($settings['hero_position']??'')===$value?'selected':'' ?>>
                        <?= $e($label) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </label>
        </div>

        <div class="notice-box">
            🎥 Los videos se reproducen con <strong>autoplay + muted + loop + playsinline</strong>,
            necesario para que los navegadores permitan reproducción automática.
        </div>

        <button class="btn btn-primary" type="submit">🎬 Guardar Hero</button>
    </form>
</section>

<section class="admin-panel" id="sections">
    <div class="admin-panel-head">
        <div><span class="eyebrow">SECTION DESIGN</span><h2>🖼 Fondos por Sección</h2></div>
    </div>

    <div class="design-section-list">
    <?php foreach($sections as $key=>$section): ?>
        <details class="design-section-card" <?= $key==='global'?'open':'' ?>>
            <summary>
                <strong><?= $e((string)$section['label']) ?></strong>
                <span><?= $e((string)$section['background_mode']) ?></span>
            </summary>

            <form method="post" action="/admin/design-studio/section" class="omega-form">
                <input type="hidden" name="_csrf" value="<?= $e(Csrf::token()) ?>">
                <input type="hidden" name="section_key" value="<?= $e($key) ?>">

                <div class="design-form-grid">
                    <label class="check-line">
                        <input type="checkbox" name="enabled" value="1" <?= !empty($section['enabled'])?'checked':'' ?>>
                        Activar personalización
                    </label>

                    <label>Tipo de fondo
                        <select name="background_mode" class="design-background-mode">
                            <?php if($key!=='global'): ?>
                                <option value="inherit" <?= $section['background_mode']==='inherit'?'selected':'' ?>>Heredar fondo global</option>
                            <?php endif; ?>
                            <option value="default" <?= $section['background_mode']==='default'?'selected':'' ?>>Diseño Omega original</option>
                            <option value="solid" <?= $section['background_mode']==='solid'?'selected':'' ?>>Color sólido del tema</option>
                            <option value="image" <?= $section['background_mode']==='image'?'selected':'' ?>>Imagen fija</option>
                            <option value="random" <?= $section['background_mode']==='random'?'selected':'' ?>>Colección aleatoria</option>
                            <option value="video" <?= $section['background_mode']==='video'?'selected':'' ?>>Video</option>
                        </select>
                    </label>

                    <label>Imagen / Video fijo
                        <select name="asset_id" class="design-fixed-asset">
                            <option value="0">— Ninguno —</option>
                            <?php foreach($assets as $a): ?>
                            <option value="<?= (int)$a['id'] ?>" <?= (int)$section['asset_id']===(int)$a['id']?'selected':'' ?>>
                                <?= $a['media_type']==='video'?'🎬':'🖼' ?> <?= $e((string)$a['label']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <label>Overlay (%)
                        <input type="number" min="0" max="90" name="overlay_opacity"
                               value="<?= (int)$section['overlay_opacity'] ?>">
                    </label>

                    <label>Rotación aleatoria (segundos)
                        <input type="number" min="0" max="3600" name="rotation_seconds"
                               value="<?= (int)$section['rotation_seconds'] ?>">
                        <small>0 = elegir una imagen distinta en cada carga.</small>
                    </label>

                    <label>Posición
                        <select name="background_position">
                            <?php foreach(['center center'=>'Centro','center top'=>'Arriba','center bottom'=>'Abajo','left center'=>'Izquierda','right center'=>'Derecha'] as $value=>$label): ?>
                            <option value="<?= $e($value) ?>" <?= $section['background_position']===$value?'selected':'' ?>>
                                <?= $e($label) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                </div>

                <div class="design-random-picker">
                    <strong>🎲 Colección de imágenes aleatorias</strong>
                    <p>Marca las imágenes que podrán aparecer en esta sección.</p>
                    <div class="design-asset-checks">
                    <?php foreach($imageAssets as $a): ?>
                        <label>
                            <input type="checkbox" name="random_asset_ids[]" value="<?= (int)$a['id'] ?>"
                                <?= in_array((int)$a['id'],$section['random_asset_ids'],true)?'checked':'' ?>>
                            <span class="mini-preview" style="background-image:url('<?= $e((string)$a['public_path']) ?>')"></span>
                            <?= $e((string)$a['label']) ?>
                        </label>
                    <?php endforeach; ?>
                    <?php if(!$imageAssets): ?><span class="muted">Primero sube imágenes a la Biblioteca.</span><?php endif; ?>
                    </div>
                </div>

                <button class="btn btn-primary" type="submit">💾 Guardar <?= $e((string)$section['label']) ?></button>
            </form>
        </details>
    <?php endforeach; ?>
    </div>
</section>

<section class="admin-panel" id="library">
    <div class="admin-panel-head">
        <div><span class="eyebrow">MEDIA MANAGER</span><h2>📁 Biblioteca Multimedia</h2></div>
    </div>

    <form method="post" action="/admin/design-studio/upload" enctype="multipart/form-data" class="omega-form design-upload-form">
        <input type="hidden" name="_csrf" value="<?= $e(Csrf::token()) ?>">
        <label>Nombre / Etiqueta
            <input name="label" maxlength="120" placeholder="Ej: Devias de noche">
        </label>
        <label>Archivo
            <input type="file" name="media" accept="image/jpeg,image/png,image/webp,image/gif,video/mp4,video/webm" required>
            <small>Imágenes máximo 12 MB · Videos máximo 60 MB · No se permite SVG.</small>
        </label>
        <button class="btn btn-primary" type="submit">⬆ Subir a Biblioteca</button>
    </form>

    <div class="design-media-grid">
    <?php foreach($assets as $a): ?>
        <article class="design-media-card">
            <div class="design-media-preview">
                <?php if($a['media_type']==='video'): ?>
                    <video muted preload="metadata">
                        <source src="<?= $e((string)$a['public_path']) ?>" type="<?= $e((string)$a['mime_type']) ?>">
                    </video>
                    <span>🎬 VIDEO</span>
                <?php else: ?>
                    <img src="<?= $e((string)$a['public_path']) ?>" alt="">
                <?php endif; ?>
            </div>
            <div class="design-media-meta">
                <strong><?= $e((string)$a['label']) ?></strong>
                <small>
                    <?= strtoupper($e((string)$a['media_type'])) ?> ·
                    <?= $e(dsBytes((int)$a['file_size'])) ?>
                    <?php if(!empty($a['width'])): ?> · <?= (int)$a['width'] ?>×<?= (int)$a['height'] ?><?php endif; ?>
                </small>
                <code><?= $e((string)$a['public_path']) ?></code>

                <form method="post" action="/admin/design-studio/asset/delete"
                      class="design-delete-form"
                      onsubmit="return confirm('¿Eliminar definitivamente este archivo de la Biblioteca Multimedia?');">
                    <input type="hidden" name="_csrf" value="<?= $e(Csrf::token()) ?>">
                    <input type="hidden" name="asset_id" value="<?= (int)$a['id'] ?>">
                    <button class="btn btn-ghost" type="submit">🗑 Eliminar</button>
                </form>
            </div>
        </article>
    <?php endforeach; ?>
    <?php if(!$assets): ?>
        <div class="empty-state compact-empty"><span class="empty-icon">🖼</span><h3>Biblioteca vacía</h3><p>Sube tu primera imagen o video.</p></div>
    <?php endif; ?>
    </div>
</section>

<section class="admin-panel" id="presets">
    <div class="admin-panel-head">
        <div><span class="eyebrow">DESIGN SNAPSHOTS</span><h2>💾 Presets</h2></div>
    </div>

    <form method="post" action="/admin/design-studio/preset/save" class="omega-form design-preset-form">
        <input type="hidden" name="_csrf" value="<?= $e(Csrf::token()) ?>">
        <label>Nombre del preset
            <input name="preset_name" maxlength="100" required placeholder="Ej: Halloween 2026">
        </label>
        <button class="btn btn-primary" type="submit">💾 Guardar diseño actual</button>
    </form>

    <div class="design-preset-grid">
    <?php foreach($presets as $p): ?>
        <div class="design-preset-card">
            <div>
                <strong><?= $e((string)$p['preset_name']) ?></strong>
                <small><?= $e((string)$p['created_at']) ?> · <?= $e((string)($p['created_by']??'')) ?></small>
            </div>
            <form method="post" action="/admin/design-studio/preset/apply"
                  onsubmit="return confirm('¿Aplicar este preset y reemplazar el diseño actual?');">
                <input type="hidden" name="_csrf" value="<?= $e(Csrf::token()) ?>">
                <input type="hidden" name="preset_id" value="<?= (int)$p['id'] ?>">
                <button class="btn btn-ghost" type="submit">🎨 Aplicar</button>
            </form>
        </div>
    <?php endforeach; ?>
    <?php if(!$presets): ?><p class="muted">Todavía no has guardado presets.</p><?php endif; ?>
    </div>
</section>


<script>
(function(){
    document.querySelectorAll('.design-section-card form').forEach(function(form){
        const mode=form.querySelector('.design-background-mode');
        const asset=form.querySelector('.design-fixed-asset');
        const sectionKey=form.querySelector('input[name="section_key"]');

        if(!mode || !asset || !sectionKey) return;

        asset.addEventListener('change',function(){
            if(asset.value !== '0') return;

            if(mode.value === 'image' || mode.value === 'video'){
                mode.value = sectionKey.value === 'global' ? 'default' : 'inherit';
            }
        });
    });
})();
</script>
