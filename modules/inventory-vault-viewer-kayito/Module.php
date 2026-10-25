<?php
declare(strict_types=1);

require_once __DIR__.'/InventoryViewerService.php';

return static function(string $hook,array $payload,array $config,array $context): mixed {
    /** @var \Omega\Core\Database $muDb */
    $muDb=$context['mu_db'];
    $iv=new OmegaInventoryViewerService($muDb);
    $e=static fn(string $v):string=>htmlspecialchars($v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');

    if($hook==='site.head'){
        return
            '<link rel="stylesheet" href="/module-assets/inventory-vault-viewer-kayito/assets/css/viewer.css?v=140">'.
            '<script defer src="/module-assets/inventory-vault-viewer-kayito/assets/js/viewer.js?v=140"></script>';
    }

    if($hook==='admin.action'){
        $action=(string)($payload['action']??'');
        $d=(array)($payload['data']??[]);

        if($action==='save_settings'){
            $iv->saveSettings($d);
            return 'Configuración del Inventario y Baúl guardada.';
        }
        if($action==='runtime_check'){
            return 'Runtime Inventario y Baúl Viewer v1.3.0 Discovery Center activo.';
        }
        if($action==='reseed_catalog'){
            $count=$iv->seedCatalog();
            return 'Catálogo revisado. Objetos agregados: '.$count.'.';
        }
        if($action==='diagnostics'){
            $diag=$iv->diagnostics();
            return 'Character.Inventory='.
                (($diag['Character.Inventory']['MaxLength']??0)?:'N/D').
                ' | warehouse.Items='.
                (($diag['warehouse.Items']['MaxLength']??0)?:'N/D').
                ' | Catálogo='.$diag['catalog_items'].
                ' | Imágenes incluidas='.$diag['provided_images'];
        }
        if($action==='save_custom_item'){
            $iv->saveCustomItem($d);
            return 'Item personalizado guardado: '.(int)($d['type']??0).':'.(int)($d['id']??0).'.';
        }
        if($action==='delete_custom_item'){
            $iv->deleteCustomItem((int)($d['type']??0),(int)($d['id']??0));
            return 'Item personalizado eliminado.';
        }
        if($action==='import_custom_items_json'){
            $result=$iv->importCustomItemsJson((string)($d['custom_items_json']??''));
            $msg='Importación completada. Guardados: '.(int)$result['saved'].'.';
            if(!empty($result['errors'])){
                $msg.=' Errores: '.implode(' | ',array_slice((array)$result['errors'],0,5));
            }
            return $msg;
        }

        return null;
    }

    if($hook==='admin.panel'){
        $s=$iv->settings();
        $diag=$iv->diagnostics();
        $allDetectedItems=$iv->scanAllItemsSummary(1000);
        $scanTotals=$iv->scanTotals();
        $unknownItems=array_values(array_filter(
            $allDetectedItems,
            static fn(array $row): bool => empty($row['catalog_found'])
        ));
        $customItems=array_values($iv->customItems());
        $customMap=[]; foreach($customItems as $ci){ $customMap[((int)$ci['type']).':'.((int)$ci['index'])]=$ci; }
        $customExport=$iv->exportCustomItemsJson();
        $imageCandidates=$iv->imageCandidates(1200);
        $token=$e((string)($payload['csrf_token']??''));
        $checked=static fn(string $key,bool $default=false):string =>
            (($s[$key]??($default?'1':'0'))==='1')?' checked':'';

        ob_start(); ?>
<style>
.ivadm-grid{display:grid;grid-template-columns:430px 1fr;gap:14px}
.ivadm-box{background:#11171e;border:1px solid #293440;border-radius:10px;padding:15px}
.ivadm-box h3{font:20px Georgia,serif;color:#e2bd5c;margin:0 0 12px}
.ivadm-form{display:grid;gap:9px}.ivadm-form label{font-size:10px;color:#aab5c0}
.ivadm-form input,.ivadm-form select,.ivadm-form textarea{width:100%;margin-top:4px;padding:8px;background:#0a1016;color:#e7edf3;border:1px solid #344153;border-radius:6px}
.ivadm-checks{display:grid;grid-template-columns:1fr 1fr;gap:6px}
.ivadm-checks label{display:flex;gap:6px;align-items:center}.ivadm-checks input{width:auto}
.ivadm-two{display:grid;grid-template-columns:1fr 1fr;gap:8px}
.ivadm-info{padding:11px;border:1px solid #345167;background:#0d1821;color:#a9c5d8;border-radius:7px;margin-bottom:12px}
.ivadm-warn{padding:11px;border:1px solid #755a20;background:#221b0d;color:#e4c978;border-radius:7px}
.ivadm-diag{width:100%;border-collapse:collapse}.ivadm-diag td,.ivadm-diag th{padding:8px;border-bottom:1px solid #2b3540;text-align:left}
@media(max-width:950px){.ivadm-grid{grid-template-columns:1fr}.ivadm-two,.ivadm-checks{grid-template-columns:1fr}}
</style>

<div class="ivadm-grid">
<section class="ivadm-box">
<h3>🎒 Inventario y Baúl Viewer</h3>
<div class="ivadm-info"><strong>Solo lectura.</strong> Este módulo no mueve, vende ni elimina objetos.</div>
<div class="ivadm-info">
<strong>Kayito Sync:</strong>
<?= (int)$scanTotals['known_occurrences'] ?> / <?= (int)$scanTotals['occurrences'] ?> apariciones reconocidas ·
Cobertura <strong><?= $e(number_format((float)$scanTotals['coverage_percent'],2)) ?>%</strong> ·
<?= (int)$scanTotals['unknown_types'] ?> Type:Index desconocidos.
</div>
<div class="ivadm-warn"><strong>Formato verificado:</strong> Character.Inventory 760 bytes; warehouse 1200 bytes; ExtWarehouse se procesa por DATALENGTH real. <strong>Catálogo:</strong> Item.txt real del GameServer, canonicalizado con 32 índices por sección.</div>

<form method="post" action="/admin/modules/action" class="ivadm-form" style="margin-top:12px">
<input type="hidden" name="_csrf" value="<?= $token ?>">
<input type="hidden" name="slug" value="inventory-vault-viewer-kayito">
<input type="hidden" name="module_action" value="save_settings">

<div class="ivadm-checks">
<?php foreach([
'enabled'=>'Módulo activo',
'show_inventory'=>'Mostrar inventario',
'show_warehouse'=>'Mostrar baúl',
'show_ext_warehouse'=>'Mostrar baúles extendidos',
'show_stats'=>'Mostrar estadísticas',
'show_guild'=>'Mostrar clan',
'show_online'=>'Mostrar Online',
'show_serial'=>'Mostrar Serial',
'show_raw_debug'=>'Diagnóstico HEX / slot',
'strict_catalog'=>'Catálogo estricto (no adivinar)',
'strict_images'=>'Imágenes estrictas (sin fuzzy)',
'show_unknown_items'=>'Mostrar Type:Index desconocidos',
'show_map'=>'Mostrar mapa',
'show_pk'=>'Mostrar PK',
'allow_online_view'=>'Permitir ver inventario conectado',
] as $k=>$label): ?>
<label><input type="checkbox" name="<?= $k ?>" value="1"<?= $checked($k,in_array($k,['enabled','show_inventory','show_warehouse','show_stats','show_guild','show_online','show_map'],true)) ?>> <?= $e($label) ?></label>
<?php endforeach; ?>
</div>

<label>Estilo visual
<select name="visual_style">
<option value="classic" <?= (($s['visual_style']??'classic')==='classic'?'selected':'') ?>>Clásico MU Online</option>
<option value="modern" <?= (($s['visual_style']??'classic')==='modern'?'selected':'') ?>>Moderno OmegaCMS</option>
</select>
</label>

<div class="ivadm-two">
<label>Bytes por objeto<input type="number" min="10" max="32" name="item_bytes" value="<?= (int)$s['item_bytes'] ?>"></label>
<label>Slots inventario<input type="number" name="inventory_slots" value="<?= (int)$s['inventory_slots'] ?>"></label>
<label>Slots baúl<input type="number" name="warehouse_slots" value="<?= (int)$s['warehouse_slots'] ?>"></label>
<label>Columnas<input type="number" name="grid_columns" value="<?= (int)$s['grid_columns'] ?>"></label>
<label>Tamaño celda<input type="number" name="cell_size" value="<?= (int)$s['cell_size'] ?>"></label>
<label>Color<input type="color" name="accent_color" value="<?= $e((string)$s['accent_color']) ?>"></label>
</div>

<label>Título<input name="title" value="<?= $e((string)$s['title']) ?>"></label>
<label>Subtítulo<textarea name="subtitle" rows="3"><?= $e((string)$s['subtitle']) ?></textarea></label>
<label>Fondo HTTPS<input name="background_image" value="<?= $e((string)$s['background_image']) ?>"></label>
<label>Aviso seguridad<textarea name="security_notice" rows="3"><?= $e((string)$s['security_notice']) ?></textarea></label>

<button class="btn btn-primary">Guardar configuración</button>
</form>
</section>

<section class="ivadm-box">
<h3>Diagnóstico</h3>
<table class="ivadm-diag">
<tr><th>Componente</th><th>Resultado</th></tr>
<tr><td>Character.Inventory</td><td><?= $e(json_encode($diag['Character.Inventory'],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)) ?></td></tr>
<tr><td>warehouse.Items</td><td><?= $e(json_encode($diag['warehouse.Items'],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)) ?></td></tr>
<tr><td>ExtWarehouse.Items</td><td><?= $e(json_encode($diag['ExtWarehouse.Items'],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)) ?></td></tr>
<tr><td>Catálogo autoritativo</td><td>Item.txt · <?= (int)$diag['catalog_items'] ?> Type:Index</td></tr>
<tr><td>Secciones Item.txt</td><td><?= $e(implode(', ',(array)$diag['catalog_sections'])) ?></td></tr>
<tr><td>MAX_ITEM_TYPE Kayito</td><td><?= (int)($diag['catalog_max_item_type']??32) ?> índices por sección</td></tr>
<tr><td>Definiciones Item.txt con Index &gt; 31</td><td><?= (int)($diag['catalog_overflow_definitions']??0) ?> · marcadas como conflictivas y no usadas para reemplazar IDs canónicos</td></tr>
<tr><td>SHA-256 Item.txt</td><td><code><?= $e((string)$diag['catalog_sha256']) ?></code></td></tr>
<tr><td>MUCore_Shop_Items legado</td><td><?= (int)$diag['legacy_mucore_catalog_items'] ?> registros · no usado para decodificar</td></tr>
<tr><td>Imágenes incluidas en este ZIP</td><td><?= (int)$diag['provided_images'] ?></td></tr>
<tr><td>Items personalizados</td><td><?= (int)$diag['custom_items_total'] ?></td></tr>
<tr><td>Type:Index desconocidos detectados</td><td><?= (int)$diag['unknown_items_total'] ?></td></tr>
<tr><td>Cobertura del escaneo web</td><td>
<?= (int)($diag['scan_totals']['known_occurrences']??0) ?> /
<?= (int)($diag['scan_totals']['occurrences']??0) ?> apariciones ·
<?= $e(number_format((float)($diag['scan_totals']['coverage_percent']??0),2)) ?>%
</td></tr>
<tr><td>Imágenes candidatas detectadas</td><td><?= (int)($diag['image_candidates_total']??0) ?></td></tr>
<tr><td>Formato MU 0.97k detectado</td><td>Warehouse 1200 bytes · 120 slots · 10 bytes/item</td></tr>
<tr><td>Pack imágenes 1/2</td><td><?= !empty($diag['image_pack']['parts']['inventory-item-images-1']['installed'])?'✓ Instalado':'No instalado' ?> · <?= (int)($diag['image_pack']['parts']['inventory-item-images-1']['images']??0) ?> GIF</td></tr>
<tr><td>Pack imágenes 2/2</td><td><?= !empty($diag['image_pack']['parts']['inventory-item-images-2']['installed'])?'✓ Instalado':'No instalado' ?> · <?= (int)($diag['image_pack']['parts']['inventory-item-images-2']['images']??0) ?> GIF</td></tr>
<tr><td>Colección dividida completa</td><td><?= !empty($diag['image_pack']['split_complete'])?'✓ SÍ':'⚠ NO' ?> · <?= (int)($diag['image_pack']['split_images']??0) ?> GIF</td></tr>
</table>

<h3 style="margin-top:18px">Muestras Inventario</h3>
<pre><?= $e(json_encode($diag['inventory_samples'],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)) ?></pre>
<h3>Muestras Warehouse</h3>
<pre><?= $e(json_encode($diag['warehouse_samples'],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)) ?></pre>
<h3>Muestras ExtWarehouse</h3>
<pre><?= $e(json_encode($diag['extwarehouse_samples'],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)) ?></pre>

<p style="color:#8796a4;font-size:11px">
El paquete WebEngine original indica que el pack completo de imágenes podía distribuirse por separado. Esta adaptación incluye exactamente los GIF/skin que venían en el ZIP proporcionado.
</p>
</section>


<section class="ivadm-box" style="grid-column:1/-1">
<h3>🧠 Kayito Item Scanner — listado completo</h3>
<div class="ivadm-info">
Este listado usa exactamente la misma fórmula del Python GUI:
<code>AbsoluteIndex → Type = /32 → Index = %32</code>.
Debe ser la referencia para comparar Web ↔ Python.
</div>

<div style="display:flex;gap:12px;flex-wrap:wrap;margin-bottom:10px">
<span class="iv-kpi">IDs únicos: <strong><?= (int)$scanTotals['unique_types'] ?></strong></span>
<span class="iv-kpi">Apariciones: <strong><?= (int)$scanTotals['occurrences'] ?></strong></span>
<span class="iv-kpi">Conocidas: <strong><?= (int)$scanTotals['known_occurrences'] ?></strong></span>
<span class="iv-kpi">Desconocidas: <strong><?= (int)$scanTotals['unknown_occurrences'] ?></strong></span>
<span class="iv-kpi">Cobertura: <strong><?= $e(number_format((float)$scanTotals['coverage_percent'],2)) ?>%</strong></span>
</div>

<div style="max-height:720px;overflow:auto;border:1px solid #26303b;border-radius:8px">
<table class="ivadm-diag">
<tr>
<th>Estado</th>
<th>Type:Index</th>
<th>Absolute</th>
<th>Nombre</th>
<th>X/Y</th>
<th>Total</th>
<th>Character</th>
<th>Warehouse</th>
<th>ExtWarehouse</th>
<th>HEX muestra</th>
<th>Imagen</th>
</tr>
<?php foreach($allDetectedItems as $row): ?>
<tr>
<td>
<?php if(!empty($row['catalog_found'])): ?>
<span style="color:#74d69b">✓ CONOCIDO</span>
<?php else: ?>
<span style="color:#ff9b77">⚠ DESCONOCIDO</span>
<?php endif; ?>
</td>
<td><strong><?= (int)$row['type'] ?>:<?= (int)$row['id'] ?></strong></td>
<td><?= (int)$row['absolute_index'] ?></td>
<td><?= $e((string)$row['name']) ?></td>
<td><?= (int)$row['x'] ?>×<?= (int)$row['y'] ?></td>
<td><?= (int)$row['count'] ?></td>
<td><?= (int)$row['from_character'] ?></td>
<td><?= (int)$row['from_warehouse'] ?></td>
<td><?= (int)$row['from_extwarehouse'] ?></td>
<td><code><?= $e((string)$row['sample_hex']) ?></code></td>
<td>
<?php if(!empty($row['image_url'])): ?>
<img src="<?= $e((string)$row['image_url']) ?>" alt="" style="max-width:36px;max-height:36px">
<?php else: ?>
<span style="color:#7f8b96">sin GIF</span>
<?php endif; ?>
</td>
</tr>
<?php endforeach; ?>
</table>
</div>
</section>

<section class="ivadm-box">
<h3>🧩 Type:Index desconocidos</h3>
<div class="ivadm-info"><strong>Sí es posible.</strong> Aquí solo aparecerán Type:Index que realmente no existan en el catálogo canónico Kayito. Una vez guardados, el visor los tratará como conocidos y podrá mostrarles nombre, tamaño e imagen.</div>
<div class="ivadm-warn"><strong>Imagen personalizada:</strong> puedes indicar una URL <code>https://...</code>, una ruta tipo <code>assets/custom-items/mi_item.gif</code> o simplemente un nombre de archivo como <code>mi_item.gif</code> y el módulo lo buscará en <code>assets/custom-items/</code>.</div>
<?php if(!$unknownItems): ?>
<p style="color:#9fb0bf">No se detectaron Type:Index desconocidos con la configuración actual.</p>
<?php else: ?>
<div style="max-height:680px;overflow:auto;border:1px solid #26303b;border-radius:8px">
<table class="ivadm-diag">
<tr><th>Type:Index</th><th>Total</th><th>Char</th><th>Baúl</th><th>Ext</th><th>Muestra</th><th>Registrar</th></tr>
<?php foreach($unknownItems as $u): $key=((int)$u['type']).':'.((int)$u['id']); $preset=$customMap[$key]??null; ?>
<tr>
<td><strong><?= (int)$u['type'] ?>:<?= (int)$u['id'] ?></strong></td>
<td><?= (int)$u['count'] ?></td>
<td><?= (int)$u['from_character'] ?></td>
<td><?= (int)$u['from_warehouse'] ?></td>
<td><?= (int)$u['from_extwarehouse'] ?></td>
<td>
<div>Owner: <?= $e((string)$u['sample_owner']) ?></div>
<div>Cuenta: <?= $e((string)$u['sample_account']) ?></div>
<div>Nivel: +<?= (int)$u['sample_level'] ?></div>
<div><code><?= $e((string)$u['sample_hex']) ?></code></div>
</td>
<td style="min-width:360px">
<form method="post" action="/admin/modules/action" class="ivadm-form">
<input type="hidden" name="_csrf" value="<?= $token ?>">
<input type="hidden" name="slug" value="inventory-vault-viewer-kayito">
<input type="hidden" name="module_action" value="save_custom_item">
<input type="hidden" name="type" value="<?= (int)$u['type'] ?>">
<input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
<div class="ivadm-two">
<label>Nombre<input name="name" value="<?= $e((string)($preset['name']??('Item '.$key))) ?>"></label>
<label>Imagen<input name="image" value="<?= $e((string)($preset['image_path']??'')) ?>" placeholder="assets/custom-items/mi_item.gif o https://..."></label>
<label>Ancho<input type="number" min="1" max="8" name="width" value="<?= (int)($preset['width']??1) ?>"></label>
<label>Alto<input type="number" min="1" max="8" name="height" value="<?= (int)($preset['height']??1) ?>"></label>
</div>
<label>Notas<input name="notes" value="<?= $e((string)($preset['notes']??'')) ?>" placeholder="Ej: item custom temporal, test, etc."></label>
<button class="btn btn-primary">Guardar mapeo</button>
</form>
</td>
</tr>
<?php endforeach; ?>
</table>
</div>
<?php endif; ?>
</section>

<section class="ivadm-box">
<h3>📚 Items personalizados cargados</h3>
<?php if(!$customItems): ?>
<p style="color:#9fb0bf">Todavía no hay items personalizados cargados.</p>
<?php else: ?>
<table class="ivadm-diag">
<tr><th>Type:Index</th><th>Nombre</th><th>Tamaño</th><th>Imagen</th><th>Notas</th><th>Acción</th></tr>
<?php foreach($customItems as $ci): ?>
<tr>
<td><?= (int)$ci['type'] ?>:<?= (int)$ci['index'] ?></td>
<td><?= $e((string)$ci['name']) ?></td>
<td><?= (int)$ci['width'] ?>x<?= (int)$ci['height'] ?></td>
<td><code><?= $e((string)$ci['image_path']) ?></code></td>
<td><?= $e((string)$ci['notes']) ?></td>
<td>
<form method="post" action="/admin/modules/action" onsubmit="return confirm('¿Eliminar este mapeo personalizado?')">
<input type="hidden" name="_csrf" value="<?= $token ?>">
<input type="hidden" name="slug" value="inventory-vault-viewer-kayito">
<input type="hidden" name="module_action" value="delete_custom_item">
<input type="hidden" name="type" value="<?= (int)$ci['type'] ?>">
<input type="hidden" name="id" value="<?= (int)$ci['index'] ?>">
<button class="btn btn-danger">Eliminar</button>
</form>
</td>
</tr>
<?php endforeach; ?>
</table>
<?php endif; ?>
</section>

<section class="ivadm-box">
<h3>📦 Importar / Exportar mapeos</h3>
<div class="ivadm-info">
Los mapeos personalizados pueden moverse entre el panel web y la herramienta Python.
El formato usa <code>Type</code>, <code>Index</code>, nombre, X/Y, imagen y notas.
</div>

<label style="display:block;font-size:10px;color:#aab5c0">Exportación actual
<textarea id="iv-custom-export" rows="14" readonly style="width:100%;margin-top:5px;padding:9px;background:#081018;color:#dbe9f5;border:1px solid #344153;border-radius:6px"><?= $e($customExport) ?></textarea>
</label>
<div style="display:flex;gap:8px;margin:8px 0 18px;flex-wrap:wrap">
<button type="button" class="btn btn-secondary" onclick="navigator.clipboard.writeText(document.getElementById('iv-custom-export').value)">Copiar JSON</button>
<button type="button" class="btn btn-secondary" onclick="const b=new Blob([document.getElementById('iv-custom-export').value],{type:'application/json'});const a=document.createElement('a');a.href=URL.createObjectURL(b);a.download='omega_custom_items.json';a.click();URL.revokeObjectURL(a.href)">Descargar JSON</button>
</div>

<form method="post" action="/admin/modules/action" class="ivadm-form">
<input type="hidden" name="_csrf" value="<?= $token ?>">
<input type="hidden" name="slug" value="inventory-vault-viewer-kayito">
<input type="hidden" name="module_action" value="import_custom_items_json">
<label>Pegar JSON para importar
<textarea name="custom_items_json" rows="12" placeholder='{"format":"omega_inventory_custom_items","items":[...]}'></textarea>
</label>
<button class="btn btn-primary">Importar mapeos</button>
</form>
</section>

<section class="ivadm-box">
<h3>🖼 Explorador de imágenes del pack</h3>
<div class="ivadm-info">
Se muestran imágenes encontradas en los packs instalados. Haz clic en una para copiar su URL y pegarla en el campo <strong>Imagen</strong> de un item personalizado.
</div>
<div class="iv-image-browser" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(110px,1fr));gap:8px;max-height:540px;overflow:auto">
<?php foreach($imageCandidates as $img): ?>
<button type="button"
        onclick="navigator.clipboard.writeText('<?= $e((string)$img['url']) ?>')"
        title="Copiar URL: <?= $e((string)$img['url']) ?>"
        style="background:#0a1016;border:1px solid #334150;border-radius:7px;padding:7px;color:#dbe6ef;cursor:pointer">
<div style="height:72px;display:flex;align-items:center;justify-content:center">
<img src="<?= $e((string)$img['url']) ?>" alt="" style="max-width:64px;max-height:68px;image-rendering:auto">
</div>
<div style="font-size:9px;word-break:break-all"><?= $e((string)$img['file']) ?></div>
</button>
<?php endforeach; ?>
</div>
</section>
</div>
        <?php
        return (string)ob_get_clean();
    }

    if($hook==='user.page'){
        $s=$iv->settings();

        if(($s['enabled']??'1')!=='1'){
            return [
                'title'=>'Inventario y Baúl',
                'html'=>'<div class="notice-box">El visor está temporalmente desactivado.</div>'
            ];
        }

        $account=trim((string)($payload['account_id']??''));
        $query=(array)($payload['query']??[]);
        $chars=$iv->characters($account);

        $selected=trim((string)($query['character']??''));
        if($selected==='' && $chars) $selected=(string)$chars[0]['Name'];

        $char=null;$vault=['Items'=>null,'Money'=>0];$extVaults=[];$selectedExt=null;$inv=[];$wh=[];$extItems=[];$error='';

        try{
            if($selected!==''){
                $char=$iv->character($account,$selected);
                $vault=$iv->warehouse($account);
                $extVaults=$iv->extWarehouses($account);

                $requestedExt=(int)($query['ext']??($extVaults[0]['Number']??0));
                foreach($extVaults as $candidateExt){
                    if((int)$candidateExt['Number']===$requestedExt){
                        $selectedExt=$candidateExt;
                        break;
                    }
                }
                if($selectedExt===null && $extVaults){
                    $selectedExt=$extVaults[0];
                }

                $canView=true;
                if(($s['allow_online_view']??'0')!=='1' && !empty($char['IsOnline'])){
                    $canView=false;
                }

                if($canView){
                    $inv=$iv->decodeBlob(
                        $char['Inventory']??null,
                        (int)$s['inventory_slots'],
                        (int)$s['item_bytes']
                    );
                    $wh=$iv->decodeBlob(
                        $vault['Items']??null,
                        (int)$s['warehouse_slots'],
                        (int)$s['item_bytes']
                    );
                    if($selectedExt!==null){
                        $extItems=$iv->decodeBlob(
                            $selectedExt['Items']??null,
                            120,
                            10
                        );
                    }
                }
            }
        }catch(\Throwable $ex){
            $error=$ex->getMessage();
        }

        $cell=max(24,(int)$s['cell_size']);
        $style=(string)($s['visual_style']??'classic');
        $accent=(string)($s['accent_color']??'#d4af37');
        $background=$iv->assetUrl((string)($s['background_image']??''));

        $showRawDebug=($s['show_raw_debug']??'0')==='1';
        $showUnknownItems=($s['show_unknown_items']??'0')==='1' || $showRawDebug;
        $tooltip=static function(array $i,bool $showSerial,bool $showRawDebug) use($e): string {
            $parts=[];
            $parts[]='<b>'.$e((string)$i['name']).' +'.(int)$i['level'].'</b>';
            $parts[]='Durabilidad: '.(int)$i['durability'];
            if(!empty($i['skill'])) $parts[]='Skill';
            if(!empty($i['luck'])) $parts[]='Luck';
            if(!empty($i['option'])) $parts[]='Opción adicional: +'.((int)$i['option']*4).'%';
            if(!empty($i['excellent'])) $parts[]='Excellent: 0x'.strtoupper(dechex((int)$i['excellent']));
            if(!empty($i['ancient'])) $parts[]='Ancient';
            if(!empty($i['harmony_type'])) $parts[]='Harmony: '.(int)$i['harmony_type'].' / '.(int)$i['harmony_value'];
            if(!empty($i['socket_capable'])) $parts[]='Compatible con Socket';
            if($showSerial) $parts[]='Serial: '.$e((string)$i['serial']);
            if($showRawDebug){
                $parts[]='Slot: '.(int)($i['slot']??-1);
                $parts[]='Type/Index: '.(int)$i['type'].':'.(int)$i['id'];
                $parts[]='HEX: '.$e((string)$i['hex']);
                $parts[]='AbsoluteIndex: '.(int)($i['absolute_index']??0);
                $parts[]='Kayito ID: '.(int)$i['type'].':'.(int)$i['id'].' (32 por sección)';
                $parts[]='Catálogo: '.((($i['catalog_source']??'')==='custom')?'MAPEO PERSONALIZADO':(!empty($i['catalog_found'])?'Item.txt EXACTO':'NO EXISTE EN Item.txt'));
                if(!empty($i['catalog_line'])) $parts[]='Item.txt línea: '.(int)$i['catalog_line'];
                if(!empty($i['custom_notes'])) $parts[]='Notas custom: '.$e((string)$i['custom_notes']);
                $parts[]='Byte7: '.$e((string)($i['byte7']??'')).' · Byte9: '.$e((string)($i['byte9']??''));
                $parts[]='Decoder: '.$e((string)($i['decoder_source']??''));
            }
            return implode('<br>',$parts);
        };

        $image=static function(array $i) use($e): string {
            if(empty($i['image_url'])) return '<span class="iv-missing">?</span>';
            return '<img src="'.$e((string)$i['image_url']).'" alt="'.$e((string)$i['name']).'">';
        };

        $grid=static function(array $items,int $cols,int $cell,bool $showSerial,bool $showRawDebug,int $offset,int $rows,bool $classic)
            use($tooltip,$image,$showUnknownItems): string {
            ob_start();
            ?><div class="iv-grid<?= $classic?' iv-grid-classic':'' ?>" style="--cols:<?= $cols ?>;--rows:<?= $rows ?>;--cell:<?= $cell ?>px"><?php
            foreach($items as $i){
                if(empty($i['catalog_found']) && !$showUnknownItems) continue;
                if((int)$i['slot']<$offset) continue;
                $visual=(int)$i['slot']-$offset;
                if($visual<0 || $visual>=($cols*$rows)) continue;
                $x=$visual%$cols;$y=(int)floor($visual/$cols);
                $classes='iv-item'.(!empty($i['excellent'])?' excellent':'').(empty($i['catalog_found'])?' unknown':'');
                ?><div class="<?= $classes ?>"
                    style="left:<?= $x*$cell ?>px;top:<?= $y*$cell ?>px;width:<?= (int)$i['x']*$cell ?>px;height:<?= (int)$i['y']*$cell ?>px"
                    data-tip="<?= htmlspecialchars($tooltip($i,$showSerial,$showRawDebug),ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8') ?>"><?= $image($i) ?></div><?php
            }
            ?></div><?php
            return (string)ob_get_clean();
        };

        $equipment=static function(array $items,bool $showSerial,bool $showRawDebug,bool $withBag)
            use($tooltip,$image,$showUnknownItems): string {
            $positions=[
                8=>[3,3,64,62,'pet'],2=>[97,3,62,62,'helm'],7=>[161,3,92,62,'wings'],
                0=>[3,67,64,94,'weapon-left'],9=>[68,67,28,28,'pendant'],
                3=>[98,67,62,94,'armor'],1=>[192,67,61,94,'weapon-right'],
                5=>[3,162,64,62,'gloves'],10=>[68,162,28,28,'ring-left'],
                4=>[98,162,62,62,'pants'],11=>[162,162,28,28,'ring-right'],
                6=>[192,162,61,62,'boots']
            ];
            ob_start(); ?><div class="iv-equipment iv-equipment-classic"><?php
            foreach($items as $i){
                if(empty($i['catalog_found']) && !$showUnknownItems) continue;
                $slot=(int)$i['slot'];
                if(!isset($positions[$slot])) continue;
                $p=$positions[$slot];
                $classes='iv-equip-slot slot-'.$p[4].(!empty($i['excellent'])?' excellent':'').(empty($i['catalog_found'])?' unknown':'');
                ?><div class="<?= $classes ?>"
                    style="left:<?= $p[0] ?>px;top:<?= $p[1] ?>px;width:<?= $p[2] ?>px;height:<?= $p[3] ?>px"
                    data-tip="<?= htmlspecialchars($tooltip($i,$showSerial,$showRawDebug),ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8') ?>">
                    <div class="iv-equip-image"><?= $image($i) ?></div>
                </div><?php
            }

            if($withBag){
                foreach($items as $i){
                    if(empty($i['catalog_found']) && !$showUnknownItems) continue;
                    $slot=(int)$i['slot'];
                    if($slot<12) continue;
                    $visual=$slot-12;
                    if($visual<0 || $visual>=64) continue;
                    $x=$visual%8;$y=(int)floor($visual/8);
                    $classes='iv-item iv-classic-bag-item'.(!empty($i['excellent'])?' excellent':'').(empty($i['catalog_found'])?' unknown':'');
                    ?><div class="<?= $classes ?>"
                        style="left:<?= $x*32 ?>px;top:<?= 224+$y*32 ?>px;width:<?= (int)$i['x']*32 ?>px;height:<?= (int)$i['y']*32 ?>px"
                        data-tip="<?= htmlspecialchars($tooltip($i,$showSerial,$showRawDebug),ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8') ?>"><?= $image($i) ?></div><?php
                }
            }
            ?></div><?php return (string)ob_get_clean();
        };

        $knownInv=array_values(array_filter($inv,static fn(array $x):bool=>!empty($x['catalog_found'])));
        $knownWh=array_values(array_filter($wh,static fn(array $x):bool=>!empty($x['catalog_found'])));
        $knownExt=array_values(array_filter($extItems,static fn(array $x):bool=>!empty($x['catalog_found'])));

        $unknownInv=count($inv)-count($knownInv);
        $unknownWh=count($wh)-count($knownWh);
        $unknownExt=count($extItems)-count($knownExt);

        $invLayout=$iv->analyzeLayout($knownInv,8,8,12);
        $whLayout=$iv->analyzeLayout($knownWh,8,15,0);
        $extLayout=$iv->analyzeLayout($knownExt,8,15,0);

        $invUsed=(int)$invLayout['used_cells'];
        $whUsed=(int)$whLayout['used_cells'];
        $extUsed=(int)$extLayout['used_cells'];
        $showSerial=($s['show_serial']??'0')==='1';

        $rootStyle='--accent:'.$e($accent).';';
        if($background!==''){
            $rootStyle.="background-image:linear-gradient(rgba(7,8,11,.9),rgba(7,8,11,.9)),url('".$e($background)."');";
        }

        ob_start(); ?>
<div id="iv-app" class="iv-style-<?= $e($style) ?>" style="<?= $rootStyle ?>">
<header><div><span>USERCP</span><h1><?= $e((string)$s['title']) ?></h1><p><?= $e((string)$s['subtitle']) ?></p></div><div>🎒</div></header>

<nav class="iv-characters">
<?php foreach($chars as $c): ?>
<a class="<?= strcasecmp($selected,(string)$c['Name'])===0?'active':'' ?>" href="?character=<?= rawurlencode((string)$c['Name']) ?>">
<?= $e((string)$c['Name']) ?><small><?= $e(OmegaInventoryViewerService::className((int)$c['Class'])) ?></small>
</a>
<?php endforeach; ?>
</nav>

<?php if($error!==''): ?><div class="iv-online-warning"><?= $e($error) ?></div><?php endif; ?>

<?php if($char): ?>
<section class="iv-profile">
<div><h2><?= $e((string)$char['Name']) ?></h2>
<p><?= $e(OmegaInventoryViewerService::className((int)$char['Class'])) ?> · Nivel <?= (int)$char['cLevel'] ?> · Master <?= (int)$char['MasterLevel'] ?></p></div>
<?php if(($s['show_online']??'1')==='1'): ?><div class="iv-online <?= !empty($char['IsOnline'])?'on':'off' ?>"><?= !empty($char['IsOnline'])?'● Conectado':'● Desconectado' ?></div><?php endif; ?>
</section>

<?php if(($s['show_stats']??'1')==='1'): ?>
<div class="iv-stats-actions"><button type="button" id="iv-toggle-stats">📊 Ocultar estadísticas</button></div>
<div class="iv-stats" id="iv-stats">
<?php foreach([
'Resets'=>$char['Resets'],'G. Reset'=>$char['MasterResets'],'Fuerza'=>$char['Strength'],
'Agilidad'=>$char['Dexterity'],'Vitalidad'=>$char['Vitality'],'Energía'=>$char['Energy'],
'Comando'=>$char['Leadership'],'Puntos'=>$char['LevelUpPoint'],'Zen'=>$char['Money']
] as $k=>$v): ?><div><span><?= $e($k) ?></span><b><?= number_format((int)$v) ?></b></div><?php endforeach; ?>
</div>
<?php endif; ?>

<?php if(($s['show_guild']??'1')==='1' && !empty($char['G_Name'])): ?>
<div class="iv-guild">🛡️ Clan <b><?= $e((string)$char['G_Name']) ?></b> · Maestro <?= $e((string)($char['G_Master']??'')) ?> · Puntos <?= number_format((int)($char['G_Score']??0)) ?></div>
<?php endif; ?>

<?php if(($s['allow_online_view']??'0')!=='1' && !empty($char['IsOnline'])): ?>
<div class="iv-online-warning">🔒 El personaje está conectado. La visualización del inventario fue bloqueada por seguridad.</div>
<?php endif; ?>

<div class="iv-panels">
<?php if(($s['show_inventory']??'1')==='1'): ?>
<section class="iv-panel iv-inventory-panel">
<h2>🎒 Inventario de <?= $e((string)$char['Name']) ?></h2>
<div class="iv-inventory-layout">
<div><h3>Equipo equipado</h3>
<?php if($style==='classic'): ?>
<div class="iv-classic-frame iv-inventory-frame"><?= $equipment($inv,$showSerial,$showRawDebug,true) ?></div>
<?php else: ?>
<div class="iv-modern-equipment"><?= $equipment($inv,$showSerial,$showRawDebug,false) ?></div>
<?php endif; ?>
</div>
<?php if($style!=='classic'): ?><div><h3>Mochila</h3><div class="iv-modern-backpack"><?= $grid($inv,8,$cell,$showSerial,$showRawDebug,12,8,false) ?></div></div><?php endif; ?>
</div>
<p class="iv-counter">🎒 <?= count($knownInv) ?> objetos Item.txt · <?= number_format($invUsed) ?> casillas usadas · <?= number_format(max(0,64-$invUsed)) ?> libres<?php if($unknownInv>0): ?> · <span class="iv-ignored"><?= (int)$unknownInv ?> bloques no catalogados ignorados</span><?php endif; ?></p>
</section>
<?php endif; ?>

<?php if(($s['show_warehouse']??'1')==='1'): ?>
<section class="iv-panel">
<h2>🏦 Baúl de la cuenta</h2>
<?php if($style==='classic'): ?>
<div class="iv-warehouse-shell"><div class="iv-warehouse-top"></div><div class="iv-warehouse-body"><div class="iv-warehouse-left"></div><?= $grid($wh,8,32,$showSerial,$showRawDebug,0,15,true) ?><div class="iv-warehouse-right"></div></div><div class="iv-warehouse-bottom"></div></div>
<?php else: ?>
<div class="iv-modern-warehouse"><?= $grid($wh,(int)$s['grid_columns'],$cell,$showSerial,$showRawDebug,0,15,false) ?></div>
<?php endif; ?>
<p class="iv-counter">🏦 <?= count($knownWh) ?> objetos Item.txt · <?= number_format($whUsed) ?> casillas usadas · <?= number_format(max(0,(int)$s['warehouse_slots']-$whUsed)) ?> libres · Zen: <?= number_format((int)$vault['Money']) ?><?php if($unknownWh>0): ?> · <span class="iv-ignored"><?= (int)$unknownWh ?> bloques no catalogados ignorados</span><?php endif; ?></p>
</section>
<?php endif; ?>

<?php if(($s['show_ext_warehouse']??'1')==='1' && $extVaults): ?>
<section class="iv-panel">
<h2>📦 Baúles extendidos</h2>

<nav class="iv-ext-tabs">
<?php foreach($extVaults as $ev): ?>
<a class="<?= ($selectedExt!==null && (int)$selectedExt['Number']===(int)$ev['Number'])?'active':'' ?>"
   href="?character=<?= rawurlencode((string)$selected) ?>&ext=<?= (int)$ev['Number'] ?>">
Baúl #<?= (int)$ev['Number'] ?>
<small><?= number_format((int)($ev['Money']??0)) ?> Zen</small>
</a>
<?php endforeach; ?>
</nav>

<?php if($selectedExt!==null): ?>
<div class="iv-format-badge">
ExtWarehouse #<?= (int)$selectedExt['Number'] ?> ·
DATALENGTH <?= (int)($selectedExt['ItemBytes']??0) ?> bytes ·
decoder 10-byte Kayito
</div>

<?php if($style==='classic'): ?>
<div class="iv-warehouse-shell">
<div class="iv-warehouse-top"></div>
<div class="iv-warehouse-body">
<div class="iv-warehouse-left"></div>
<?= $grid($extItems,8,32,$showSerial,$showRawDebug,0,15,true) ?>
<div class="iv-warehouse-right"></div>
</div>
<div class="iv-warehouse-bottom"></div>
</div>
<?php else: ?>
<div class="iv-modern-warehouse">
<?= $grid($extItems,8,$cell,$showSerial,$showRawDebug,0,15,false) ?>
</div>
<?php endif; ?>

<p class="iv-counter">
📦 <?= count($knownExt) ?> objetos Item.txt ·
<?= number_format($extUsed) ?> casillas ocupadas ·
<?= number_format((int)$extLayout['free_cells']) ?> libres ·
Zen: <?= number_format((int)($selectedExt['Money']??0)) ?><?php if($unknownExt>0): ?> · <span class="iv-ignored"><?= (int)$unknownExt ?> bloques no catalogados ignorados</span><?php endif; ?>
</p>

<?php if(!$extLayout['valid']): ?>
<div class="iv-layout-warning">
<strong>Diagnóstico de ocupación:</strong>
<?= $e(implode(' | ',array_slice($extLayout['warnings'],0,5))) ?>
</div>
<?php endif; ?>
<?php endif; ?>
</section>
<?php endif; ?>
</div>
<?php endif; ?>

<section class="iv-legend">
<span><i class="normal"></i> Catálogo exacto</span><span><i class="excellent"></i> Excellent</span><span><i class="unknown"></i> Type:Index no catalogado</span>
</section>
<footer>🔐 <?= $e((string)$s['security_notice']) ?></footer>
<div id="iv-tooltip"></div>
</div>
        <?php
        return [
            'title'=>(string)($s['title']??'Inventario y Baúl'),
            'html'=>(string)ob_get_clean()
        ];
    }

    return null;
};
