<?php
declare(strict_types=1);

require_once __DIR__.'/NameEffectsService.php';

return static function(string $hook,array $payload,array $config,array $context): mixed {
    /** @var \Omega\Core\Database $db */
    $db=$context['mu_db'];
    $service=new OmegaNameEffectsService($db,$config,__DIR__);
    $e=static fn(string $v):string=>htmlspecialchars($v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');

    if($hook==='character.name.render'){
        return $service->render((string)($payload['name']??''));
    }

    if($hook==='site.head'){
        return '<style id="omega-name-effects-css">.omega-name-effect{display:inline-block;padding:2px 8px;border-radius:5px;line-height:1.25;font-weight:inherit;text-decoration:inherit}</style>';
    }

    if($hook==='admin.action'){
        $action=(string)($payload['action']??'');
        $d=(array)($payload['data']??[]);
        if($action==='runtime_check'){
            return 'Runtime Name Effects v1.1.4 activo. No se está ejecutando código antiguo.';
        }
        if($action==='cleanup_expired'){
            $n=$service->cleanupExpired();
            return "Limpieza completada: {$n} asignación(es) vencidas.";
        }
        if($action==='effect_save'){
            $service->saveEffect($d);return 'Efecto guardado.';
        }
        if($action==='effect_toggle'){
            $service->toggle((int)($d['effect_id']??0));return 'Estado actualizado.';
        }
        if($action==='effect_delete'){
            $service->delete((int)($d['effect_id']??0));return 'Efecto eliminado.';
        }
        return null;
    }

    if($hook==='admin.panel'){
        $catalog=$service->catalog(false);
        $logs=$service->logs(80);
        $token=$e((string)($payload['csrf_token']??''));
        $sample=$e((string)($config['default_sample_text']??'Azzlaer'));
        ob_start(); ?>
<style>
.ne-admin-grid{display:grid;grid-template-columns:390px 1fr;gap:14px}.ne-box{background:#11171e;border:1px solid #293440;border-radius:10px;padding:15px}.ne-box h3{font:20px Georgia,serif;margin:0 0 12px}.ne-form{display:grid;gap:9px}.ne-form label{font-size:9px;color:#9ba8b6}.ne-form input,.ne-form select,.ne-form textarea{width:100%;margin-top:4px;background:#0b1117;color:#e7edf3;border:1px solid #344153;border-radius:6px;padding:8px}.ne-row{display:grid;grid-template-columns:1fr 1fr;gap:8px}.ne-table{width:100%;border-collapse:collapse}.ne-table th,.ne-table td{padding:9px;border-bottom:1px solid #27323d;font-size:9px;text-align:left}.ne-preview{font-weight:800;font-size:14px}.ne-actions{display:flex;gap:4px}.ne-actions form{display:inline}.ne-log{max-height:300px;overflow:auto}@media(max-width:900px){.ne-admin-grid{grid-template-columns:1fr}}
</style>
<div class="ne-admin-grid">
<section class="ne-box">
<h3>✨ Crear efecto</h3>
<form method="post" action="/admin/modules/action" class="ne-form">
<input type="hidden" name="_csrf" value="<?= $token ?>">
<input type="hidden" name="slug" value="name-effects">
<input type="hidden" name="module_action" value="effect_save">
<input type="hidden" name="EffectID" value="0">
<label>Nombre<input name="EffectName" required maxlength="120" placeholder="Fuego Infernal"></label>
<label>Clave<input name="EffectKey" required maxlength="80" placeholder="fuego-infernal"></label>
<label>Imagen/fondo opcional<input name="ImagePath" maxlength="500" placeholder="https://... o /public/..."></label>
<div class="ne-row"><label>Color texto<input type="color" name="TextColor" value="#ffffff"></label><label>Color glow<input type="color" name="GlowColor" value="#d4af37"></label></div>
<label>Intensidad glow<input type="range" name="GlowSize" min="0" max="25" value="7"></label>
<div class="ne-row"><label>Tipo costo<select name="CostType"><?php foreach(['FREE','ZEN','WCOINC','LEVEL','POINTS','RESETS','GRANDRESETS'] as $t): ?><option><?= $e($t) ?></option><?php endforeach; ?></select></label><label>Costo<input type="number" name="CostAmount" min="0" value="0"></label></div>
<div class="ne-row"><label>Duración<select name="DurationType"><option value="days">Por días</option><option value="permanent">Permanente</option></select></label><label>Días<input type="number" name="DurationDays" min="1" max="3650" value="30"></label></div>
<label>CSS visual adicional<textarea name="CustomCss" rows="3" placeholder="font-weight:800;letter-spacing:1px;"></textarea></label>
<label><input type="checkbox" name="FreeAllowed" value="1" checked> Disponible Free</label>
<label><input type="checkbox" name="VipAllowed" value="1" checked> Disponible VIP</label>
<label><input type="checkbox" name="IsActive" value="1" checked> Activo</label>
<label>Orden<input type="number" name="SortOrder" value="0"></label>
<label>Notas<textarea name="Notes" rows="2"></textarea></label>
<button class="btn btn-primary">Guardar efecto</button>
</form>
</section>

<section class="ne-box">
<h3>Catálogo actual</h3>
<div style="overflow:auto">
<table class="ne-table"><thead><tr><th>ID</th><th>Vista previa</th><th>Efecto</th><th>Costo</th><th>Duración</th><th>Estado</th><th></th></tr></thead><tbody>
<?php foreach($catalog as $fx):
$style='color:'.$fx['TextColor'].';';
if((int)$fx['GlowSize']>0)$style.='text-shadow:0 0 '.(int)$fx['GlowSize'].'px '.$fx['GlowColor'].';';
if(!empty($fx['ImagePath'])){
    $fxUrl=$service->assetUrl((string)$fx['ImagePath']);
    if($fxUrl!==''){
        $style.='background-image:url(\''.str_replace("'","%27",$fxUrl).'\');background-repeat:repeat-x;background-position:center center;background-size:auto 100%;padding:2px 8px;';
    }
}
$style.=(string)$fx['CustomCss'];
?>
<tr>
<td><?= (int)$fx['EffectID'] ?></td>
<td><span class="ne-preview" style="<?= $e($style) ?>"><?= $sample ?></span></td>
<td><strong><?= $e((string)$fx['EffectName']) ?></strong><br><small><?= $e((string)$fx['EffectKey']) ?></small></td>
<td><?= number_format((int)$fx['CostAmount']) ?> <?= $e((string)$fx['CostType']) ?></td>
<td><?= $fx['DurationType']==='permanent'?'Permanente':((int)$fx['DurationDays'].' días') ?></td>
<td><?= (int)$fx['IsActive']?'Activo':'Inactivo' ?></td>
<td><div class="ne-actions">
<form method="post" action="/admin/modules/action"><input type="hidden" name="_csrf" value="<?= $token ?>"><input type="hidden" name="slug" value="name-effects"><input type="hidden" name="module_action" value="effect_toggle"><input type="hidden" name="effect_id" value="<?= (int)$fx['EffectID'] ?>"><button class="btn btn-ghost">Estado</button></form>
<form method="post" action="/admin/modules/action" onsubmit="return confirm('¿Eliminar efecto?')"><input type="hidden" name="_csrf" value="<?= $token ?>"><input type="hidden" name="slug" value="name-effects"><input type="hidden" name="module_action" value="effect_delete"><input type="hidden" name="effect_id" value="<?= (int)$fx['EffectID'] ?>"><button class="btn btn-danger">Eliminar</button></form>
</div></td>
</tr>
<?php endforeach; ?>
<?php if(!$catalog): ?><tr><td colspan="7" class="empty-cell">No hay efectos todavía.</td></tr><?php endif; ?>
</tbody></table></div>
</section>
</div>

<section class="ne-box" style="margin-top:14px">
<h3>Últimas operaciones</h3>
<div class="ne-log"><table class="ne-table"><thead><tr><th>Cuenta</th><th>Personaje</th><th>Efecto</th><th>Perfil</th><th>Costo</th><th>Estado</th><th>Fecha</th></tr></thead><tbody>
<?php foreach($logs as $log): ?><tr><td><?= $e((string)$log['AccountID']) ?></td><td><?= $e((string)$log['CharacterName']) ?></td><td><?= $e((string)$log['EffectName']) ?></td><td><?= $e((string)$log['UserTier']) ?></td><td><?= number_format((int)$log['CostAmount']) ?> <?= $e((string)$log['CostType']) ?></td><td><?= $e((string)$log['Status']) ?></td><td><?= $e((string)$log['CreatedAt']) ?></td></tr><?php endforeach; ?>
</tbody></table></div>
</section>
        <?php return (string)ob_get_clean();
    }

    if($hook==='user.page'){
        $account=(string)($payload['account_id']??'');
        $post=(array)($payload['post']??[]);
        if(($payload['method']??'GET')==='POST'){
            if(isset($post['buy_effect'])){
                $service->buy($account,(string)($post['character']??''),(int)$post['buy_effect']);
            }elseif(isset($post['remove_effect'])){
                $service->remove($account,(string)$post['remove_effect']);
            }
        }

        $chars=$service->characters($account);
        $catalog=$service->catalog(true);
        $tier=$service->userTier($account);
        $token=$e((string)($payload['csrf_token']??''));
        $sample=(string)($config['default_sample_text']??'Azzlaer');

        ob_start(); ?>
<style>
.ne-user{color:#dbe5ef}.ne-hero{padding:22px;border-radius:12px;background:linear-gradient(135deg,#111b28,#172431);border:1px solid #2c4053}.ne-tier{display:inline-flex;padding:4px 8px;border-radius:999px;background:#d3a84a;color:#15110a;font-size:8px;font-weight:900}.ne-hero h2{font:28px Georgia,serif;margin:8px 0}.ne-hero p{color:#91a1b0;margin:0}.ne-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:14px}.ne-card{background:#11171e;border:1px solid #293440;border-radius:10px;padding:15px}.ne-card h3{font:20px Georgia,serif;margin:0 0 12px}.ne-active{display:flex;justify-content:space-between;align-items:center;padding:9px 0;border-bottom:1px solid #27313c}.ne-preview{font-weight:800;font-size:17px}.ne-catalog{display:grid;grid-template-columns:repeat(2,1fr);gap:8px}.ne-fx{background:#0d1319;border:1px solid #293440;border-radius:8px;padding:12px;text-align:center}.ne-fx-preview{font-size:18px;font-weight:800;min-height:48px;display:flex;align-items:center;justify-content:center}.ne-fx small{color:#718092}.ne-fx form{margin-top:9px}.ne-fx select{width:100%;background:#0a1016;color:#ddd;border:1px solid #344153;border-radius:5px;padding:7px;margin-bottom:7px}@media(max-width:760px){.ne-grid{grid-template-columns:1fr}.ne-catalog{grid-template-columns:1fr}}
</style>
<div class="ne-user">
<header class="ne-hero"><span class="ne-tier"><?= $e($tier) ?></span><h2>✨ <?= $e((string)($config['module_title']??'Efectos para tu Nombre')) ?></h2><p><?= $e((string)($config['module_description']??'')) ?></p></header>

<div class="ne-grid">
<section class="ne-card">
<h3>Mis personajes</h3>
<?php foreach($chars as $char): $active=$service->activeFor((string)$char['Name']); ?>
<div class="ne-active">
<div>
<?php if($active): echo $service->render((string)$char['Name']); else: ?><strong><?= $e((string)$char['Name']) ?></strong><?php endif; ?>
<br><small><?= $active?'Efecto: '.$e((string)$active['EffectName']):'Sin efecto activo' ?></small>
</div>
<?php if($active): ?><form method="post"><input type="hidden" name="_csrf" value="<?= $token ?>"><button class="btn btn-ghost" name="remove_effect" value="<?= $e((string)$char['Name']) ?>">Quitar</button></form><?php endif; ?>
</div>
<?php endforeach; ?>
</section>

<section class="ne-card">
<h3>Catálogo</h3>
<div class="ne-catalog">
<?php foreach($catalog as $fx):
$preview=$service->render($sample);
$style='color:'.$fx['TextColor'].';';
if((int)$fx['GlowSize']>0)$style.='text-shadow:0 0 '.(int)$fx['GlowSize'].'px '.$fx['GlowColor'].';';
if(!empty($fx['ImagePath'])){
    $fxUrl=$service->assetUrl((string)$fx['ImagePath']);
    if($fxUrl!==''){
        $style.='background-image:url(\''.str_replace("'","%27",$fxUrl).'\');background-repeat:repeat-x;background-position:center center;background-size:auto 100%;padding:2px 8px;';
    }
}
$style.=(string)$fx['CustomCss'];
?>
<article class="ne-fx">
<div class="ne-fx-preview"><span style="<?= $e($style) ?>"><?= $e($sample) ?></span></div>
<strong><?= $e((string)$fx['EffectName']) ?></strong><br>
<small><?= number_format((int)$fx['CostAmount']) ?> <?= $e((string)$fx['CostType']) ?> · <?= $fx['DurationType']==='permanent'?'Permanente':((int)$fx['DurationDays'].' días') ?></small>
<form method="post">
<input type="hidden" name="_csrf" value="<?= $token ?>">
<select name="character" required><option value="">Selecciona personaje</option><?php foreach($chars as $ch): ?><option value="<?= $e((string)$ch['Name']) ?>"><?= $e((string)$ch['Name']) ?></option><?php endforeach; ?></select>
<button class="btn btn-primary" name="buy_effect" value="<?= (int)$fx['EffectID'] ?>">Aplicar efecto</button>
</form>
</article>
<?php endforeach; ?>
</div>
</section>
</div>
</div>
        <?php
        return ['title'=>'Efectos de Nombre','html'=>(string)ob_get_clean()];
    }

    return null;
};
