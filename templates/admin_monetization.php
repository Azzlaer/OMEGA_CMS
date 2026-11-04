<?php
use Omega\Core\Security;
use Omega\Core\Csrf;
$e=[Security::class,'e'];
$settings=$settings??[];
$rows=$rows??[];
$item=$item??[];
$edit=!empty($item['id']);
$placements=[
 'global_header'=>'Global · antes del contenido',
 'below_navigation'=>'Debajo de navegación',
 'content_top'=>'Inicio del contenido',
 'content_bottom'=>'Final del contenido',
 'global_footer'=>'Antes del footer',
 'home_after_news'=>'Inicio · después de noticias',
 'rankings_top'=>'Rankings · superior',
 'rankings_bottom'=>'Rankings · inferior',
 'guides_top'=>'Guías · superior',
 'guides_between_cards'=>'Guías · entre tarjetas',
 'guide_article_middle'=>'Guía · artículo',
 'market_sidebar'=>'Market · sidebar',
 'character_profile_bottom'=>'Personaje · inferior'
];
?>
<?php if(!empty($success)): ?><div class="form-alert success wide"><?= $e($success) ?></div><?php endif; ?>
<?php if(!empty($error)): ?><div class="form-alert error wide"><?= $e($error) ?></div><?php endif; ?>

<div class="admin-grid monetization-admin-grid">
<section class="admin-panel">
    <div class="admin-panel-head">
        <div><span class="eyebrow">GOOGLE SERVICES</span><h2>Google & Monetization Center PRO</h2></div>
    </div>
    <form method="post" action="/admin/monetization/settings" class="omega-form admin-form">
        <input type="hidden" name="_csrf" value="<?= $e(Csrf::token()) ?>">
        <label class="check-line"><input type="checkbox" name="enabled" value="1" <?= ($settings['enabled']??'0')==='1'?'checked':'' ?>><span>Activar Monetization Center</span></label>

        <div class="form-two">
            <label>GA4 Measurement ID<input name="ga4_id" value="<?= $e((string)($settings['ga4_id']??'')) ?>" placeholder="G-XXXXXXXXXX"></label>
            <label>Google Ads ID<input name="google_ads_id" value="<?= $e((string)($settings['google_ads_id']??'')) ?>" placeholder="AW-123456789"></label>
        </div>
        <div class="form-two">
            <label>Google Tag Manager<input name="gtm_id" value="<?= $e((string)($settings['gtm_id']??'')) ?>" placeholder="GTM-XXXXXXX"></label>
            <label>AdSense Publisher ID<input name="adsense_client" value="<?= $e((string)($settings['adsense_client']??'')) ?>" placeholder="ca-pub-1234567890123456"></label>
        </div>

        <label class="check-line"><input type="checkbox" name="adsense_auto_ads" value="1" <?= ($settings['adsense_auto_ads']??'0')==='1'?'checked':'' ?>><span>Permitir Auto Ads (el script global de AdSense quedará disponible)</span></label>
        <label class="check-line"><input type="checkbox" name="hide_ads_logged" value="1" <?= ($settings['hide_ads_logged']??'0')==='1'?'checked':'' ?>><span>Ocultar bloques a usuarios conectados</span></label>
        <label class="check-line"><input type="checkbox" name="hide_ads_admin" value="1" <?= ($settings['hide_ads_admin']??'1')==='1'?'checked':'' ?>><span>Ocultar bloques a administradores</span></label>

        <hr>
        <h3>Consentimiento</h3>
        <label class="check-line"><input type="checkbox" name="consent_enabled" value="1" <?= ($settings['consent_enabled']??'1')==='1'?'checked':'' ?>><span>Mostrar gestor de consentimiento</span></label>
        <label>Título<input name="consent_title" maxlength="100" value="<?= $e((string)($settings['consent_title']??'')) ?>"></label>
        <label>Texto<textarea name="consent_text" rows="3" maxlength="700"><?= $e((string)($settings['consent_text']??'')) ?></textarea></label>
        <label>Política de privacidad<input name="consent_policy_url" value="<?= $e((string)($settings['consent_policy_url']??'')) ?>" placeholder="/privacy"></label>

        <hr>
        <h3>ads.txt</h3>
        <label class="check-line"><input type="checkbox" name="ads_txt_enabled" value="1" <?= ($settings['ads_txt_enabled']??'0')==='1'?'checked':'' ?>><span>Publicar /ads.txt desde OMEGA</span></label>
        <label>Contenido ads.txt<textarea name="ads_txt_content" rows="6" spellcheck="false" placeholder="google.com, pub-XXXXXXXXXXXXXXXX, DIRECT, f08c47fec0942fa0"><?= $e((string)($settings['ads_txt_content']??'')) ?></textarea></label>
        <div class="admin-form-actions"><button class="btn btn-primary" type="submit">Guardar configuración</button><a class="btn btn-ghost" href="/ads.txt" target="_blank">↗ Ver ads.txt</a></div>
    </form>
</section>

<section class="admin-panel">
    <div class="admin-panel-head">
        <div><span class="eyebrow">PLACEMENTS</span><h2><?= $edit?'Editar bloque':'Nuevo bloque' ?></h2></div>
    </div>
    <form method="post" action="/admin/monetization/placement/save" class="omega-form admin-form">
        <input type="hidden" name="_csrf" value="<?= $e(Csrf::token()) ?>">
        <input type="hidden" name="id" value="<?= (int)($item['id']??0) ?>">
        <label>Nombre<input name="name" maxlength="120" required value="<?= $e((string)($item['name']??'')) ?>" placeholder="Footer AdSense"></label>
        <label>Placement
            <select name="placement_key">
            <?php foreach($placements as $key=>$label): ?><option value="<?= $e($key) ?>" <?= ($item['placement_key']??'')===$key?'selected':'' ?>><?= $e($label) ?> (<?= $e($key) ?>)</option><?php endforeach; ?>
            </select>
        </label>
        <div class="form-two">
            <label>Proveedor<select name="provider">
                <?php foreach(['adsense'=>'Google AdSense','image'=>'Banner imagen','house'=>'House Ad','html'=>'HTML seguro'] as $k=>$v): ?><option value="<?= $k ?>" <?= ($item['provider']??'house')===$k?'selected':'' ?>><?= $v ?></option><?php endforeach; ?>
            </select></label>
            <label>Orden<input type="number" min="0" max="9999" name="sort_order" value="<?= (int)($item['sort_order']??100) ?>"></label>
        </div>
        <div class="form-two">
            <label>Audiencia<select name="audience"><?php foreach(['all'=>'Todos','guests'=>'Solo visitantes','members'=>'Solo miembros'] as $k=>$v): ?><option value="<?= $k ?>" <?= ($item['audience']??'all')===$k?'selected':'' ?>><?= $v ?></option><?php endforeach; ?></select></label>
            <label>Dispositivo<select name="device"><?php foreach(['all'=>'Todos','desktop'=>'Desktop','mobile'=>'Mobile'] as $k=>$v): ?><option value="<?= $k ?>" <?= ($item['device']??'all')===$k?'selected':'' ?>><?= $v ?></option><?php endforeach; ?></select></label>
        </div>
        <label>Rutas (opcional)<input name="path_rule" maxlength="700" value="<?= $e((string)($item['path_rule']??'')) ?>" placeholder="/guides*, /rankings"></label>
        <small>Usa rutas exactas o prefijos con *. Vacío = todas las páginas.</small>

        <div class="form-two">
            <label>AdSense Slot<input name="adsense_slot" value="<?= $e((string)($item['adsense_slot']??'')) ?>" placeholder="1234567890"></label>
            <label>Formato<select name="ad_format"><?php foreach(['auto','rectangle','horizontal','vertical','fluid'] as $v): ?><option value="<?= $v ?>" <?= ($item['ad_format']??'auto')===$v?'selected':'' ?>><?= ucfirst($v) ?></option><?php endforeach; ?></select></label>
        </div>
        <label>Imagen / banner URL<input name="image_url" value="<?= $e((string)($item['image_url']??'')) ?>" placeholder="https://.../banner.webp"></label>
        <label>Destino del banner<input name="target_url" value="<?= $e((string)($item['target_url']??'')) ?>" placeholder="https://..."></label>
        <label>Etiqueta / texto<input name="label_text" maxlength="180" value="<?= $e((string)($item['label_text']??'')) ?>" placeholder="Publicidad / Promo VIP / ..."></label>
        <label>HTML seguro<textarea name="html_code" rows="5" spellcheck="false"><?= $e((string)($item['html_code']??'')) ?></textarea></label>
        <div class="form-two">
            <label>Inicio<input type="datetime-local" name="starts_at" value="<?= !empty($item['starts_at'])?$e(date('Y-m-d\TH:i',strtotime((string)$item['starts_at']))):'' ?>"></label>
            <label>Fin<input type="datetime-local" name="ends_at" value="<?= !empty($item['ends_at'])?$e(date('Y-m-d\TH:i',strtotime((string)$item['ends_at']))):'' ?>"></label>
        </div>
        <label class="check-line"><input type="checkbox" name="enabled" value="1" <?= !isset($item['enabled'])||!empty($item['enabled'])?'checked':'' ?>><span>Bloque activo</span></label>
        <div class="admin-form-actions">
            <?php if($edit): ?><a class="btn btn-ghost" href="/admin/monetization">Cancelar edición</a><?php endif; ?>
            <button class="btn btn-primary" type="submit">Guardar bloque</button>
        </div>
    </form>
</section>
</div>

<section class="admin-panel" style="margin-top:18px">
<div class="admin-panel-head"><div><span class="eyebrow">INVENTARIO</span><h2>Bloques configurados</h2></div></div>
<table class="admin-table"><thead><tr><th>Placement</th><th>Nombre</th><th>Proveedor</th><th>Audiencia</th><th>Estado</th><th></th></tr></thead><tbody>
<?php foreach($rows as $r): ?>
<tr>
<td><code><?= $e((string)$r['placement_key']) ?></code></td>
<td><strong><?= $e((string)$r['name']) ?></strong><small><?= $e((string)($r['path_rule']?:'Todas las rutas')) ?></small></td>
<td><?= $e(strtoupper((string)$r['provider'])) ?></td>
<td><?= $e((string)$r['audience']) ?> · <?= $e((string)$r['device']) ?></td>
<td><?= !empty($r['enabled'])?'● Activo':'○ Pausado' ?></td>
<td class="admin-actions-cell"><a href="/admin/monetization?edit=<?= (int)$r['id'] ?>">Editar</a><form method="post" action="/admin/monetization/placement/delete" onsubmit="return confirm('¿Eliminar este bloque?')"><input type="hidden" name="_csrf" value="<?= $e(Csrf::token()) ?>"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button>Eliminar</button></form></td>
</tr>
<?php endforeach; ?>
<?php if(!$rows): ?><tr><td colspan="6" class="empty-cell">Todavía no hay bloques publicitarios.</td></tr><?php endif; ?>
</tbody></table>
</section>