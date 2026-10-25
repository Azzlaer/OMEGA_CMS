<?php
declare(strict_types=1);

require_once __DIR__.'/VipMembershipService.php';

return static function(string $hook,array $payload,array $config,array $context): mixed {
    /** @var \Omega\Core\Database $cmsDb */
    $cmsDb=$context['cms_db'];
    /** @var \Omega\Core\Database $muDb */
    $muDb=$context['mu_db'];

    $service=new OmegaVipMembershipService($cmsDb,$muDb);
    $e=static fn(string $v):string=>htmlspecialchars($v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');

    if($hook==='site.head'){
        return
            '<link rel="stylesheet" href="/module-assets/vip-membership-pro/assets/css/vipmembershippro.css?v=100">'.
            '<script defer src="/module-assets/vip-membership-pro/assets/js/vipmembershippro.js?v=100"></script>';
    }

    if($hook==='admin.action'){
        $action=(string)($payload['action']??'');
        $data=(array)($payload['data']??[]);

        if($action==='save_settings'){
            $service->saveSettings($data);
            return 'Configuración VIP guardada.';
        }

        if($action==='plan_add'){
            $service->addPlan($data);
            return 'Plan VIP agregado.';
        }

        if($action==='plan_save'){
            $service->savePlan($data);
            return 'Plan VIP actualizado.';
        }

        if($action==='plan_delete'){
            $service->deletePlan((int)($data['PlanID']??0));
            return 'Plan eliminado o deshabilitado.';
        }

        if($action==='runtime_check'){
            return 'Runtime VIP Membership Pro para OmegaCMS v1.0.1 activo.';
        }

        if($action==='diagnostics'){
            $d=$service->diagnostics();
            $parts=[];
            foreach($d as $label=>$ok){
                $parts[]=$label.': '.($ok?'OK':'FALTA');
            }
            return implode(' | ',$parts);
        }

        return null;
    }

    if($hook==='admin.panel'){
        $s=$service->settings();
        $plans=$service->plans(false);
        $logs=$service->logs(80);
        $token=$e((string)($payload['csrf_token']??''));

        $checked=static fn(string $key,bool $default=false):string =>
            (($s[$key]??($default?'1':'0'))==='1')?' checked':'';

        ob_start(); ?>
<style>
.vipadm-grid{display:grid;grid-template-columns:420px 1fr;gap:14px}
.vipadm-box{background:#11171e;border:1px solid #293440;border-radius:10px;padding:15px}
.vipadm-box h3{font:20px Georgia,serif;color:#f1ca65;margin:0 0 12px}
.vipadm-form{display:grid;gap:9px}
.vipadm-form label{font-size:10px;color:#a9b5c1}
.vipadm-form input,.vipadm-form textarea,.vipadm-form select{width:100%;margin-top:4px;background:#0a1016;color:#e7edf3;border:1px solid #344153;border-radius:6px;padding:8px}
.vipadm-check{display:flex;gap:7px;align-items:center;color:#dce5ec!important}
.vipadm-check input{width:auto}
.vipadm-two{display:grid;grid-template-columns:1fr 1fr;gap:8px}
.vipadm-plan{border:1px solid #2d3945;border-radius:8px;padding:12px;margin-bottom:10px;background:#0d1319}
.vipadm-plan-head{display:flex;justify-content:space-between;align-items:center;margin-bottom:9px}
.vipadm-table{width:100%;border-collapse:collapse}
.vipadm-table th,.vipadm-table td{padding:8px;border-bottom:1px solid #27313d;font-size:10px;text-align:left}
.vipadm-help{color:#8392a0;font-size:10px;line-height:1.5}
@media(max-width:950px){.vipadm-grid{grid-template-columns:1fr}.vipadm-two{grid-template-columns:1fr}}
</style>

<div class="vipadm-grid">
<section class="vipadm-box">
<h3>👑 VIP Membership Pro</h3>

<form method="post" action="/admin/modules/action" class="vipadm-form">
<input type="hidden" name="_csrf" value="<?= $token ?>">
<input type="hidden" name="slug" value="vip-membership-pro">
<input type="hidden" name="module_action" value="save_settings">

<label class="vipadm-check"><input type="checkbox" name="enabled" value="1"<?= $checked('enabled',true) ?>> Servicio VIP habilitado</label>
<label class="vipadm-check"><input type="checkbox" name="allow_type_change" value="1"<?= $checked('allow_type_change') ?>> Permitir cambiar de tipo VIP antes del vencimiento</label>
<label class="vipadm-check"><input type="checkbox" name="require_offline" value="1"<?= $checked('require_offline',true) ?>> Exigir cuenta desconectada</label>

<label>Título<input name="title" maxlength="120" value="<?= $e((string)($s['title']??'')) ?>"></label>
<label>Descripción<textarea name="description" rows="3"><?= $e((string)($s['description']??'')) ?></textarea></label>
<label>Nombre de moneda<input name="currency_name" maxlength="80" value="<?= $e((string)($s['currency_name']??'Créditos')) ?>"></label>

<h3 style="margin-top:10px">Destino VIP</h3>
<div class="vipadm-two">
<label>Tabla<input name="vip_table" value="<?= $e((string)$s['vip_table']) ?>"></label>
<label>Cuenta<input name="vip_user_column" value="<?= $e((string)$s['vip_user_column']) ?>"></label>
<label>Tipo VIP<input name="vip_type_column" value="<?= $e((string)$s['vip_type_column']) ?>"></label>
<label>Vencimiento<input name="vip_expire_column" value="<?= $e((string)$s['vip_expire_column']) ?>"></label>
</div>

<h3 style="margin-top:10px">Moneda / créditos</h3>
<label>Backend
<select name="credit_backend">
<option value="table" <?= (($s['credit_backend']??'table')==='table'?'selected':'') ?>>Tabla de créditos</option>
<option value="wcoinc" <?= (($s['credit_backend']??'')==='wcoinc'?'selected':'') ?>>WCoinC / CashShopData</option>
</select>
</label>

<div class="vipadm-two">
<label>Tabla créditos<input name="credits_table" value="<?= $e((string)$s['credits_table']) ?>"></label>
<label>Cuenta créditos<input name="credits_user_column" value="<?= $e((string)$s['credits_user_column']) ?>"></label>
<label>Columna créditos<input name="credits_value_column" value="<?= $e((string)$s['credits_value_column']) ?>"></label>
</div>

<div class="vipadm-two">
<label>Tabla WCoin<input name="wcoin_table" value="<?= $e((string)$s['wcoin_table']) ?>"></label>
<label>Cuenta WCoin<input name="wcoin_user_column" value="<?= $e((string)$s['wcoin_user_column']) ?>"></label>
<label>Columna WCoinC<input name="wcoin_value_column" value="<?= $e((string)$s['wcoin_value_column']) ?>"></label>
</div>

<h3 style="margin-top:10px">Estado Online</h3>
<div class="vipadm-two">
<label>Tabla<input name="status_table" value="<?= $e((string)$s['status_table']) ?>"></label>
<label>Cuenta<input name="status_user_column" value="<?= $e((string)$s['status_user_column']) ?>"></label>
<label>Estado<input name="status_value_column" value="<?= $e((string)$s['status_value_column']) ?>"></label>
</div>

<p class="vipadm-help">
La versión WebEngine 1.1.2 dependía de <code>CreditSystem</code>. OmegaCMS no posee esa clase, por eso esta adaptación utiliza un backend explícito y auditable.
</p>

<button class="btn btn-primary" type="submit">Guardar configuración</button>
</form>
</section>

<section class="vipadm-box">
<h3>Planes VIP</h3>

<?php foreach($plans as $plan): ?>
<div class="vipadm-plan">
<div class="vipadm-plan-head"><strong><?= $e((string)$plan['CardIcon']) ?> <?= $e((string)$plan['DisplayName']) ?></strong><span>VIP <?= (int)$plan['VipType'] ?></span></div>
<form method="post" action="/admin/modules/action" class="vipadm-form">
<input type="hidden" name="_csrf" value="<?= $token ?>">
<input type="hidden" name="slug" value="vip-membership-pro">
<input type="hidden" name="module_action" value="plan_save">
<input type="hidden" name="PlanID" value="<?= (int)$plan['PlanID'] ?>">

<div class="vipadm-two">
<label>Nombre<input name="DisplayName" value="<?= $e((string)$plan['DisplayName']) ?>"></label>
<label>Botón<input name="ButtonLabel" value="<?= $e((string)$plan['ButtonLabel']) ?>"></label>
<label>Icono<input name="CardIcon" value="<?= $e((string)$plan['CardIcon']) ?>"></label>
<label>Estilo<select name="AccentClass"><?php foreach(['bronze','silver','gold','purple','red','blue'] as $a): ?><option value="<?= $a ?>" <?= $plan['AccentClass']===$a?'selected':'' ?>><?= ucfirst($a) ?></option><?php endforeach; ?></select></label>
<label>Tipo VIP<input type="number" min="1" max="10" name="VipType" value="<?= (int)$plan['VipType'] ?>"></label>
<label>Días<input type="number" min="1" max="3650" name="DaysAmount" value="<?= (int)$plan['DaysAmount'] ?>"></label>
<label>Costo<input type="number" min="0" name="BaseCost" value="<?= (int)$plan['BaseCost'] ?>"></label>
<label>Descuento %<input type="number" min="0" max="100" name="DiscountPercent" value="<?= (int)$plan['DiscountPercent'] ?>"></label>
<label>Orden<input type="number" min="0" name="SortOrder" value="<?= (int)$plan['SortOrder'] ?>"></label>
</div>

<label>Descripción<input name="Description" value="<?= $e((string)$plan['Description']) ?>"></label>
<label>Información BBCode<textarea name="InfoContent" rows="7"><?= $e((string)$plan['InfoContent']) ?></textarea></label>
<label class="vipadm-check"><input type="checkbox" name="IsEnabled" value="1" <?= (int)$plan['IsEnabled']?'checked':'' ?>> Plan habilitado</label>
<button class="btn btn-primary" type="submit">Guardar plan</button>
</form>

<form method="post" action="/admin/modules/action" style="margin-top:6px" onsubmit="return confirm('¿Eliminar o deshabilitar este plan?')">
<input type="hidden" name="_csrf" value="<?= $token ?>">
<input type="hidden" name="slug" value="vip-membership-pro">
<input type="hidden" name="module_action" value="plan_delete">
<input type="hidden" name="PlanID" value="<?= (int)$plan['PlanID'] ?>">
<button class="btn btn-danger" type="submit">Eliminar / deshabilitar</button>
</form>
</div>
<?php endforeach; ?>

<h3 style="margin-top:14px">Agregar nuevo VIP</h3>
<form method="post" action="/admin/modules/action" class="vipadm-form">
<input type="hidden" name="_csrf" value="<?= $token ?>">
<input type="hidden" name="slug" value="vip-membership-pro">
<input type="hidden" name="module_action" value="plan_add">

<div class="vipadm-two">
<label>Nombre<input name="DisplayName" required></label>
<label>Botón<input name="ButtonLabel" value="Comprar VIP"></label>
<label>Icono<input name="CardIcon" value="👑"></label>
<label>Estilo<select name="AccentClass"><option>bronze</option><option>silver</option><option selected>gold</option><option>purple</option><option>red</option><option>blue</option></select></label>
<label>Tipo VIP<input type="number" name="VipType" min="1" max="10" value="1"></label>
<label>Días<input type="number" name="DaysAmount" min="1" value="30"></label>
<label>Costo<input type="number" name="BaseCost" min="0" value="1000"></label>
<label>Descuento %<input type="number" name="DiscountPercent" min="0" max="100" value="0"></label>
<label>Orden<input type="number" name="SortOrder" value="100"></label>
</div>
<label>Descripción<input name="Description"></label>
<label>Información BBCode<textarea name="InfoContent" rows="7">[center][size=20][b]Beneficios VIP[/b][/size][/center]

[list]
[*]Experiencia adicional
[*]Drop adicional
[*]Acceso premium
[/list]</textarea></label>
<button class="btn btn-primary">Agregar plan</button>
</form>

<h3 style="margin-top:18px">Últimas operaciones</h3>
<div style="overflow:auto">
<table class="vipadm-table">
<thead><tr><th>Cuenta</th><th>Plan</th><th>VIP</th><th>Costo</th><th>Estado</th><th>Fecha</th></tr></thead>
<tbody>
<?php foreach($logs as $log): ?>
<tr>
<td><?= $e((string)$log['AccountID']) ?></td>
<td><?= $e((string)($log['DisplayName']??'-')) ?></td>
<td><?= (int)$log['VipType'] ?></td>
<td><?= number_format((int)$log['CreditsCharged']) ?></td>
<td><?= $e((string)$log['Status']) ?></td>
<td><?= $e((string)$log['CreatedAt']) ?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
</section>
</div>
        <?php
        return (string)ob_get_clean();
    }

    if($hook==='user.page'){
        $account=trim((string)($payload['account_id']??''));
        $method=strtoupper((string)($payload['method']??'GET'));
        $post=(array)($payload['post']??[]);
        $token=$e((string)($payload['csrf_token']??''));

        $message='';
        $error='';

        if($method==='POST' && isset($post['vip_buy'])){
            try{
                $service->buy(
                    $account,
                    (int)($post['plan_id']??0),
                    (string)($payload['client_ip']??($_SERVER['REMOTE_ADDR']??''))
                );
                $message='La membresía VIP fue acreditada correctamente.';
            }catch(\Throwable $ex){
                $error=$ex->getMessage();
            }
        }

        $s=$service->settings();

        if(($s['enabled']??'0')!=='1'){
            return [
                'title'=>'Membresías VIP',
                'html'=>'<div class="vippro-error"><div>⚠️</div><section><h2>VIP Membership</h2><p>El servicio está temporalmente deshabilitado.</p></section></div>'
            ];
        }

        try{
            $balance=$service->balance($account);
            $current=$service->currentVip($account);
        }catch(\Throwable $ex){
            $balance=0;
            $current=['VipType'=>0,'VipExpire'=>null];
            if($error==='') $error=$ex->getMessage();
        }

        $plans=$service->plans(true);
        $currency=(string)($s['currency_name']??'Créditos');

        ob_start(); ?>
<div class="we-vippro">
<?php if($message!==''): ?><div class="vippro-message success"><?= $e($message) ?></div><?php endif; ?>
<?php if($error!==''): ?><div class="vippro-message error"><?= $e($error) ?></div><?php endif; ?>

<header class="vippro-hero">
<div>
<span class="vippro-eyebrow">MEMBRESÍAS PREMIUM</span>
<h1>👑 <?= $e((string)($s['title']??'Membresías VIP')) ?></h1>
<p><?= $e((string)($s['description']??'')) ?></p>
</div>
<div class="vippro-balance">
<small>Saldo disponible</small>
<strong><?= number_format($balance) ?></strong>
<span><?= $e($currency) ?></span>
</div>
</header>

<?php
$type=(int)($current['VipType']??0);
$expire=(string)($current['VipExpire']??'');
$active=$type>0 && $expire!=='' && strtotime($expire)>time();
?>
<div class="vippro-status <?= $active?'active':'' ?>">
<div class="vippro-status-icon"><?= $active?'👑':'♙' ?></div>
<div>
<small>Estado de membresía</small>
<strong><?= $active?'VIP '.$type:'Sin VIP activo' ?></strong>
<?php if($active): ?><span>Vence: <?= $e($expire) ?></span><?php endif; ?>
</div>
</div>

<div class="vippro-grid">
<?php foreach($plans as $plan):
$cost=$service->planCost($plan);
$modal='vippro-info-'.(int)$plan['PlanID'];
$accent=$e((string)($plan['AccentClass']??'gold'));
?>
<article class="vippro-card accent-<?= $accent ?>">
<div class="vippro-card-glow"></div>
<div class="vippro-card-icon"><?= $e((string)($plan['CardIcon']??'👑')) ?></div>
<span class="vippro-tier-label">VIP <?= (int)$plan['VipType'] ?></span>
<h2><?= $e((string)$plan['DisplayName']) ?></h2>
<p><?= $e((string)$plan['Description']) ?></p>
<div class="vippro-duration"><strong><?= (int)$plan['DaysAmount'] ?></strong><span>días</span></div>

<?php if((int)$plan['DiscountPercent']>0): ?>
<div class="vippro-discount">-<?= (int)$plan['DiscountPercent'] ?>%</div>
<?php endif; ?>

<div class="vippro-price"><strong><?= number_format($cost) ?></strong><span><?= $e($currency) ?></span></div>

<div class="vippro-actions">
<button type="button" class="vippro-info-button" data-vippro-open="<?= $e($modal) ?>">Ver información</button>
<form method="post" class="vippro-buy-form">
<input type="hidden" name="_csrf" value="<?= $token ?>">
<input type="hidden" name="plan_id" value="<?= (int)$plan['PlanID'] ?>">
<button type="submit" name="vip_buy" value="1"><?= $e((string)($plan['ButtonLabel']??'Comprar VIP')) ?></button>
</form>
</div>
</article>

<div class="vippro-modal" id="<?= $e($modal) ?>" aria-hidden="true">
<div class="vippro-modal-backdrop" data-vippro-close></div>
<section class="vippro-modal-dialog">
<button type="button" class="vippro-modal-close" data-vippro-close>×</button>
<div class="vippro-modal-icon"><?= $e((string)($plan['CardIcon']??'👑')) ?></div>
<h2><?= $e((string)$plan['DisplayName']) ?></h2>
<div class="vippro-modal-content"><?= $service->parseBbcode((string)$plan['InfoContent']) ?></div>
</section>
</div>
<?php endforeach; ?>
</div>
</div>
        <?php
        $html=(string)ob_get_clean();

        return [
            'title'=>(string)($s['title']??'Membresías VIP'),
            'html'=>$html
        ];
    }

    return null;
};
