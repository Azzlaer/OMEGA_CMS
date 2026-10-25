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
            '<link rel="stylesheet" href="/module-assets/inventory-vault-viewer/assets/css/viewer.css?v=100">'.
            '<script defer src="/module-assets/inventory-vault-viewer/assets/js/viewer.js?v=100"></script>';
    }

    if($hook==='admin.action'){
        $action=(string)($payload['action']??'');
        $d=(array)($payload['data']??[]);

        if($action==='save_settings'){
            $iv->saveSettings($d);
            return 'Configuración del Inventario y Baúl guardada.';
        }
        if($action==='runtime_check'){
            return 'Runtime Inventario y Baúl Viewer v1.0.2 activo.';
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

        return null;
    }

    if($hook==='admin.panel'){
        $s=$iv->settings();
        $diag=$iv->diagnostics();
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
<div class="ivadm-warn"><strong>Formato recomendado para MuEmu 0.97k:</strong> 16 bytes por objeto, 120 bloques y Warehouse de 1920 bytes.</div>

<form method="post" action="/admin/modules/action" class="ivadm-form" style="margin-top:12px">
<input type="hidden" name="_csrf" value="<?= $token ?>">
<input type="hidden" name="slug" value="inventory-vault-viewer">
<input type="hidden" name="module_action" value="save_settings">

<div class="ivadm-checks">
<?php foreach([
'enabled'=>'Módulo activo',
'show_inventory'=>'Mostrar inventario',
'show_warehouse'=>'Mostrar baúl',
'show_stats'=>'Mostrar estadísticas',
'show_guild'=>'Mostrar clan',
'show_online'=>'Mostrar Online',
'show_serial'=>'Mostrar Serial',
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
<label>Bytes por objeto<input type="number" min="16" max="32" name="item_bytes" value="<?= (int)$s['item_bytes'] ?>"></label>
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
<tr><td>Catálogo de objetos</td><td><?= (int)$diag['catalog_items'] ?> registros</td></tr>
<tr><td>Imágenes incluidas en este ZIP</td><td><?= (int)$diag['provided_images'] ?></td></tr>
<tr><td>Pack complementario</td><td><?= !empty($diag['image_pack']['companion_installed'])?'✓ Instalado':'No instalado' ?></td></tr>
<tr><td>GIF del pack complementario</td><td><?= (int)($diag['image_pack']['companion_images']??0) ?></td></tr>
</table>

<h3 style="margin-top:18px">Muestras Inventario</h3>
<pre><?= $e(json_encode($diag['inventory_samples'],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)) ?></pre>
<h3>Muestras Warehouse</h3>
<pre><?= $e(json_encode($diag['warehouse_samples'],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)) ?></pre>

<p style="color:#8796a4;font-size:11px">
El paquete WebEngine original indica que el pack completo de imágenes podía distribuirse por separado. Esta adaptación incluye exactamente los GIF/skin que venían en el ZIP proporcionado.
</p>
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

        $char=null;$vault=['Items'=>null,'Money'=>0];$inv=[];$wh=[];$error='';

        try{
            if($selected!==''){
                $char=$iv->character($account,$selected);
                $vault=$iv->warehouse($account);

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
                }
            }
        }catch(\Throwable $ex){
            $error=$ex->getMessage();
        }

        $cell=max(24,(int)$s['cell_size']);
        $style=(string)($s['visual_style']??'classic');
        $accent=(string)($s['accent_color']??'#d4af37');
        $background=$iv->assetUrl((string)($s['background_image']??''));

        $tooltip=static function(array $i,bool $showSerial) use($e): string {
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
            return implode('<br>',$parts);
        };

        $image=static function(array $i) use($e): string {
            if(empty($i['image_url'])) return '<span class="iv-missing">?</span>';
            return '<img src="'.$e((string)$i['image_url']).'" alt="'.$e((string)$i['name']).'">';
        };

        $grid=static function(array $items,int $cols,int $cell,bool $showSerial,int $offset,int $rows,bool $classic)
            use($tooltip,$image): string {
            ob_start();
            ?><div class="iv-grid<?= $classic?' iv-grid-classic':'' ?>" style="--cols:<?= $cols ?>;--rows:<?= $rows ?>;--cell:<?= $cell ?>px"><?php
            foreach($items as $i){
                if((int)$i['slot']<$offset) continue;
                $visual=(int)$i['slot']-$offset;
                if($visual<0 || $visual>=($cols*$rows)) continue;
                $x=$visual%$cols;$y=(int)floor($visual/$cols);
                $classes='iv-item'.(!empty($i['excellent'])?' excellent':'').(!empty($i['ancient'])?' ancient':'');
                ?><div class="<?= $classes ?>"
                    style="left:<?= $x*$cell ?>px;top:<?= $y*$cell ?>px;width:<?= (int)$i['x']*$cell ?>px;height:<?= (int)$i['y']*$cell ?>px"
                    data-tip="<?= htmlspecialchars($tooltip($i,$showSerial),ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8') ?>"><?= $image($i) ?></div><?php
            }
            ?></div><?php
            return (string)ob_get_clean();
        };

        $equipment=static function(array $items,bool $showSerial,bool $withBag)
            use($tooltip,$image): string {
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
                $slot=(int)$i['slot'];
                if(!isset($positions[$slot])) continue;
                $p=$positions[$slot];
                $classes='iv-equip-slot slot-'.$p[4].(!empty($i['excellent'])?' excellent':'').(!empty($i['ancient'])?' ancient':'');
                ?><div class="<?= $classes ?>"
                    style="left:<?= $p[0] ?>px;top:<?= $p[1] ?>px;width:<?= $p[2] ?>px;height:<?= $p[3] ?>px"
                    data-tip="<?= htmlspecialchars($tooltip($i,$showSerial),ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8') ?>">
                    <div class="iv-equip-image"><?= $image($i) ?></div>
                </div><?php
            }

            if($withBag){
                foreach($items as $i){
                    $slot=(int)$i['slot'];
                    if($slot<12) continue;
                    $visual=$slot-12;
                    if($visual<0 || $visual>=64) continue;
                    $x=$visual%8;$y=(int)floor($visual/8);
                    $classes='iv-item iv-classic-bag-item'.(!empty($i['excellent'])?' excellent':'').(!empty($i['ancient'])?' ancient':'');
                    ?><div class="<?= $classes ?>"
                        style="left:<?= $x*32 ?>px;top:<?= 224+$y*32 ?>px;width:<?= (int)$i['x']*32 ?>px;height:<?= (int)$i['y']*32 ?>px"
                        data-tip="<?= htmlspecialchars($tooltip($i,$showSerial),ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8') ?>"><?= $image($i) ?></div><?php
                }
            }
            ?></div><?php return (string)ob_get_clean();
        };

        $occupied=static function(array $items): int {
            $used=0;
            foreach($items as $i) $used+=max(1,(int)$i['x'])*max(1,(int)$i['y']);
            return $used;
        };

        $invUsed=$occupied($inv);
        $whUsed=$occupied($wh);
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
<div class="iv-classic-frame iv-inventory-frame"><?= $equipment($inv,$showSerial,true) ?></div>
<?php else: ?>
<div class="iv-modern-equipment"><?= $equipment($inv,$showSerial,false) ?></div>
<?php endif; ?>
</div>
<?php if($style!=='classic'): ?><div><h3>Mochila</h3><div class="iv-modern-backpack"><?= $grid($inv,8,$cell,$showSerial,12,8,false) ?></div></div><?php endif; ?>
</div>
<p class="iv-counter">🎒 <?= count($inv) ?> objetos · <?= number_format($invUsed) ?> casillas usadas · <?= number_format(max(0,(int)$s['inventory_slots']-$invUsed)) ?> libres</p>
</section>
<?php endif; ?>

<?php if(($s['show_warehouse']??'1')==='1'): ?>
<section class="iv-panel">
<h2>🏦 Baúl de la cuenta</h2>
<?php if($style==='classic'): ?>
<div class="iv-warehouse-shell"><div class="iv-warehouse-top"></div><div class="iv-warehouse-body"><div class="iv-warehouse-left"></div><?= $grid($wh,8,32,$showSerial,0,15,true) ?><div class="iv-warehouse-right"></div></div><div class="iv-warehouse-bottom"></div></div>
<?php else: ?>
<div class="iv-modern-warehouse"><?= $grid($wh,(int)$s['grid_columns'],$cell,$showSerial,0,15,false) ?></div>
<?php endif; ?>
<p class="iv-counter">🏦 <?= count($wh) ?> objetos · <?= number_format($whUsed) ?> casillas usadas · <?= number_format(max(0,(int)$s['warehouse_slots']-$whUsed)) ?> libres · Zen: <?= number_format((int)$vault['Money']) ?></p>
</section>
<?php endif; ?>
</div>
<?php endif; ?>

<section class="iv-legend">
<span><i class="normal"></i> Normal</span><span><i class="excellent"></i> Excellent</span><span><i class="ancient"></i> Ancient</span><span><i class="harmony"></i> Harmony</span><span><i class="socket"></i> Socket</span>
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
