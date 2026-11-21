<?php
declare(strict_types=1);

use Omega\Core\Security;

require_once __DIR__.'/AdsterraPublisherService.php';

return static function(string $hook,array $payload,array $config,array $context): mixed {
    /** @var \Omega\Core\Database $cmsDb */
    $cmsDb=$context['cms_db'];
    $service=new OmegaAdsterraPublisherService($cmsDb,$config);
    $e=static fn(mixed $v):string=>Security::e($v);

    if($hook==='security.csp'){
        return $service->cspOrigins();
    }

    if($hook==='site.head'){
        $ctx=[
            'path'=>(string)($payload['path']??'/'),
            'logged'=>$payload['logged']??null,
            'is_admin'=>!empty($payload['is_admin'])
        ];
        $html='<link rel="stylesheet" href="/module-assets/adsterra-publisher-pro/assets/adsterra.css?v=100">';
        $html.=$service->renderHead($ctx,Security::nonce());
        return $html;
    }

    if($hook==='site.zone'){
        $zone=(string)($payload['zone']??'');
        if($zone==='') return null;
        return $service->renderZone($zone,[
            'path'=>(string)($payload['path']??'/'),
            'logged'=>$payload['logged']??null,
            'is_admin'=>!empty($payload['is_admin'])
        ],Security::nonce());
    }

    if($hook==='admin.action'){
        $action=(string)($payload['action']??'');
        $data=(array)($payload['data']??[]);

        if($action==='save_unit'){
            $id=$service->saveUnit($data);
            return 'Unidad Adsterra guardada (#'.$id.').';
        }
        if($action==='delete_unit'){
            $service->deleteUnit((int)($data['unit_id']??0));
            return 'Unidad Adsterra eliminada.';
        }
        if($action==='api_test'){
            $r=$service->testApi();
            return 'Publisher API conectada correctamente · HTTP '.$r['status'].'.';
        }
        if($action==='runtime_check'){
            $d=$service->diagnostic();
            return 'Adsterra PRO activo · unidades '.$d['units'].
                ' · activas '.$d['enabled_units'].
                ' · hosts CSP '.count($d['csp_origins']).
                ' · API '.($d['api_key_present']?'configurada':'sin token').'.';
        }
        return null;
    }

    if($hook==='admin.panel'){
        $units=$service->units();
        $diag=$service->diagnostic();
        $stats=null;
        $statsError=null;
        try{
            if($service->apiConfigured()) $stats=$service->stats();
        }catch(Throwable $ex){
            $statsError=$ex->getMessage();
        }

        $token=$e((string)($payload['csrf_token']??''));

        $sum=['impressions'=>0.0,'clicks'=>0.0,'revenue'=>0.0];
        $rows=is_array($stats['rows']??null)?$stats['rows']:[];
        foreach($rows as $row){
            if(!is_array($row)) continue;
            foreach(['impressions','clicks','revenue'] as $k){
                $v=$row[$k]??$row[ucfirst($k)]??0;
                if(is_numeric($v)) $sum[$k]+=(float)$v;
            }
        }
        $ctr=$sum['impressions']>0?($sum['clicks']/$sum['impressions']*100):0.0;
        $cpm=$sum['impressions']>0?($sum['revenue']/$sum['impressions']*1000):0.0;

        ob_start(); ?>
<style>
.asp-grid{display:grid;grid-template-columns:minmax(0,1.05fr) minmax(320px,.95fr);gap:14px}
.asp-box{background:#11171e;border:1px solid #293642;border-radius:10px;padding:15px}
.asp-box h3{margin:0 0 12px;color:#f0ca72;font:19px Georgia,serif}
.asp-note{padding:11px 12px;border:1px solid #314252;border-radius:8px;background:#0b131a;color:#9aabba;line-height:1.5}
.asp-form{display:grid;gap:9px}
.asp-form label{font-size:9px;color:#a7b4bf}
.asp-form input,.asp-form select,.asp-form textarea{width:100%;margin-top:4px;background:#091017;border:1px solid #344352;border-radius:6px;color:#e8eef3;padding:8px}
.asp-form textarea{min-height:130px;font-family:Consolas,monospace;font-size:10px}
.asp-check{display:flex!important;align-items:center;gap:8px}.asp-check input{width:auto;margin:0}
.asp-two{display:grid;grid-template-columns:1fr 1fr;gap:9px}
.asp-stats{display:grid;grid-template-columns:repeat(4,1fr);gap:8px}
.asp-stat{padding:12px;border:1px solid #2b3945;border-radius:8px;background:#0a1118}
.asp-stat span{display:block;color:#718394;font-size:8px;text-transform:uppercase;letter-spacing:.08em}
.asp-stat strong{display:block;color:#f0cb72;font-size:20px;margin-top:4px}
.asp-table{width:100%;border-collapse:collapse;font-size:9px}
.asp-table th,.asp-table td{padding:7px;border-bottom:1px solid #25313b;text-align:left;vertical-align:top}
.asp-table th{color:#738596;text-transform:uppercase;font-size:8px}
.asp-format{display:inline-block;padding:3px 6px;border:1px solid #3d4b56;border-radius:999px;color:#9dafbd}
.asp-ok{color:#69dca6}.asp-bad{color:#ee8c98}
.asp-code{max-width:240px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;color:#718394}
@media(max-width:950px){.asp-grid{grid-template-columns:1fr}.asp-stats{grid-template-columns:1fr 1fr}.asp-two{grid-template-columns:1fr}}
</style>

<div class="asp-stats">
<div class="asp-stat"><span>UNIDADES</span><strong><?= count($units) ?></strong></div>
<div class="asp-stat"><span>ACTIVAS</span><strong><?= (int)$diag['enabled_units'] ?></strong></div>
<div class="asp-stat"><span>HOSTS CSP</span><strong><?= count($diag['csp_origins']) ?></strong></div>
<div class="asp-stat"><span>PUBLISHER API</span><strong class="<?= $diag['api_key_present']?'asp-ok':'asp-bad' ?>"><?= $diag['api_key_present']?'READY':'OFF' ?></strong></div>
</div>

<?php if($service->apiConfigured()): ?>
<section class="asp-box" style="margin-top:14px">
<h3>📊 Publisher API · <?= $e((string)($stats['start']??'')) ?> → <?= $e((string)($stats['finish']??'')) ?></h3>
<?php if($statsError): ?><div class="notice-box danger"><?= $e($statsError) ?></div><?php else: ?>
<div class="asp-stats">
<div class="asp-stat"><span>IMPRESIONES</span><strong><?= number_format($sum['impressions'],0,',','.') ?></strong></div>
<div class="asp-stat"><span>CLICKS</span><strong><?= number_format($sum['clicks'],0,',','.') ?></strong></div>
<div class="asp-stat"><span>CTR</span><strong><?= number_format($ctr,2) ?>%</strong></div>
<div class="asp-stat"><span>CPM APROX.</span><strong><?= number_format($cpm,4) ?></strong></div>
</div>
<p class="muted-copy">Ingresos reportados por el API: <strong>$<?= number_format($sum['revenue'],4) ?></strong>. El formato exacto depende de la respuesta de tu cuenta Publisher.</p>
<?php endif; ?>
</section>
<?php endif; ?>

<div class="asp-grid" style="margin-top:14px">
<section class="asp-box">
<h3>➕ Nueva unidad Adsterra</h3>
<div class="asp-note">Crea primero el ad unit en Adsterra, espera que quede <b>Active</b> y pega aquí el código obtenido con <b>GET CODE</b>. Para Smartlink pega solamente la URL.</div>
<form method="post" action="/admin/modules/action" class="asp-form" style="margin-top:12px">
<input type="hidden" name="_csrf" value="<?= $token ?>">
<input type="hidden" name="slug" value="adsterra-publisher-pro">
<input type="hidden" name="module_action" value="save_unit">

<div class="asp-two">
<label>Nombre<input name="Name" maxlength="140" required placeholder="Banner Home 728x90"></label>
<label>Formato
<select name="FormatCode">
<option value="banner">Display Banner</option>
<option value="native_banner">Native Banner</option>
<option value="social_bar">Social Bar / In-Page Push / Interstitial</option>
<option value="popunder">Popunder</option>
<option value="smartlink">Smartlink / Direct Link</option>
</select></label>
</div>

<div class="asp-two">
<label>Zona
<select name="PlacementKey">
<option value="head">HEAD / scripts globales</option>
<option value="global_header">Global Header</option>
<option value="below_navigation">Debajo navegación</option>
<option value="content_top">Inicio del contenido</option>
<option value="content_bottom" selected>Final del contenido</option>
<option value="global_footer">Global Footer</option>
</select></label>
<label>Orden<input type="number" name="SortOrder" min="0" max="9999" value="100"></label>
</div>

<div class="asp-two">
<label>Audiencia
<select name="Audience"><option value="all">Todos</option><option value="guests">Invitados</option><option value="members">Miembros</option></select></label>
<label>Dispositivo
<select name="Device"><option value="all">Todos</option><option value="desktop">Desktop</option><option value="mobile">Mobile</option></select></label>
</div>

<label>Rutas permitidas
<input name="PathRule" maxlength="700" placeholder="*, /, /guides*, /rankings">
<small>Vacío = todo el sitio. Admite rutas exactas o prefijos terminados en *.</small>
</label>

<label>Código oficial Adsterra
<textarea name="CodeHtml" placeholder="<script>...</script>"></textarea>
<small>Para Banner, Native, Popunder o Social Bar. OMEGA añadirá nonce CSP a los scripts.</small>
</label>

<label>Smartlink / Direct Link URL
<input name="SmartlinkUrl" maxlength="1200" placeholder="https://...">
</label>
<label>Sub ID opcional
<input name="SubId" maxlength="120" placeholder="home_button_1">
<small>OMEGA añadirá ?psid= o &psid= automáticamente.</small>
</label>

<label>Hosts CSP adicionales
<textarea name="CspHosts" style="min-height:75px" placeholder="https://example-ad-host.com"></textarea>
<small>Los hosts src/href del código se detectan automáticamente. Agrega aquí dominios secundarios si el navegador bloquea alguno.</small>
</label>

<div class="asp-two">
<label>Inicio opcional<input type="datetime-local" name="StartsAt"></label>
<label>Fin opcional<input type="datetime-local" name="EndsAt"></label>
</div>

<label class="asp-check"><input type="checkbox" name="Enabled" value="1" checked> Unidad activa</label>
<label class="asp-check"><input type="checkbox" name="RequiresConsent" value="1" checked> Requiere consentimiento de publicidad</label>

<button class="btn btn-primary" type="submit">Guardar unidad</button>
</form>
</section>

<aside>
<section class="asp-box">
<h3>🛡 Integración segura</h3>
<div class="asp-note">
El API Key se configura arriba como secreto cifrado. Los scripts publicitarios reciben un nonce CSP temporal por request. OMEGA no habilita globalmente <code>unsafe-inline</code> para JavaScript.
</div>
<div class="admin-status-list" style="margin-top:10px">
<div><span>API habilitada</span><strong><?= $diag['api_enabled']?'Sí':'No' ?></strong></div>
<div><span>Token presente</span><strong><?= $diag['api_key_present']?'Sí':'No' ?></strong></div>
<div><span>Unidades activas</span><strong><?= (int)$diag['enabled_units'] ?></strong></div>
</div>
</section>

<section class="asp-box" style="margin-top:14px">
<h3>Formatos soportados</h3>
<div class="admin-status-list">
<div><span>Popunder</span><strong>✓</strong></div>
<div><span>Social Bar</span><strong>✓</strong></div>
<div><span>In-Page Push / Interstitial</span><strong>Social Bar</strong></div>
<div><span>Native Banner</span><strong>✓</strong></div>
<div><span>Display Banner</span><strong>✓</strong></div>
<div><span>Smartlink / Direct Link</span><strong>✓ + psid</strong></div>
</div>
</section>
</aside>
</div>

<section class="asp-box" style="margin-top:14px">
<h3>Unidades configuradas</h3>
<table class="asp-table">
<thead><tr><th>ID</th><th>Nombre</th><th>Formato</th><th>Zona</th><th>Audiencia</th><th>Rutas</th><th>CSP</th><th>Estado</th><th></th></tr></thead>
<tbody>
<?php foreach($units as $u): ?>
<tr>
<td><?= (int)$u['Id'] ?></td>
<td><strong><?= $e((string)$u['Name']) ?></strong><div class="asp-code"><?= $e((string)($u['SmartlinkUrl']??$u['CodeHtml']??'')) ?></div></td>
<td><span class="asp-format"><?= $e((string)$u['FormatCode']) ?></span></td>
<td><?= $e((string)$u['PlacementKey']) ?></td>
<td><?= $e((string)$u['Audience']) ?> / <?= $e((string)$u['Device']) ?></td>
<td><?= $e((string)($u['PathRule']??'*')) ?></td>
<td><?= count(array_filter(preg_split('/\s+/',trim((string)($u['CspHosts']??'')))?:[])) ?> host(s)</td>
<td><?= (int)$u['Enabled']?'<span class="asp-ok">ACTIVA</span>':'<span class="asp-bad">OFF</span>' ?></td>
<td>
<form method="post" action="/admin/modules/action" onsubmit="return confirm('¿Eliminar esta unidad?')">
<input type="hidden" name="_csrf" value="<?= $token ?>">
<input type="hidden" name="slug" value="adsterra-publisher-pro">
<input type="hidden" name="module_action" value="delete_unit">
<input type="hidden" name="unit_id" value="<?= (int)$u['Id'] ?>">
<button class="btn btn-danger" type="submit">Eliminar</button>
</form>
</td>
</tr>
<?php endforeach; ?>
<?php if(!$units): ?><tr><td colspan="9" class="muted-text">Todavía no hay unidades configuradas.</td></tr><?php endif; ?>
</tbody>
</table>
</section>
<?php
        return (string)ob_get_clean();
    }

    return null;
};
