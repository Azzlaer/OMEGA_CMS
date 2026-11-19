<?php
declare(strict_types=1);

require_once __DIR__.'/LiveTrafficService.php';

return static function(string $hook,array $payload,array $config,array $context): mixed {
    /** @var \Omega\Core\Database $cmsDb */
    $cmsDb=$context['cms_db'];
    $service=new OmegaLiveTrafficService($cmsDb);
    $e=static fn(string $v):string=>htmlspecialchars($v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');

    if($hook==='site.head'){
        $path=(string)(parse_url($_SERVER['REQUEST_URI']??'/',PHP_URL_PATH)?:'/');
        $service->track($path);

        $s=$service->settings();
        if(($s['enabled']??'0')!=='1') return null;

        $stats=$service->stats();
        $attrs=[
            'data-widget-type'=>(string)($s['widget_type']??'summary'),
            'data-placement'=>(string)($s['placement']??'footer'),
            'data-refresh'=>(string)($s['refresh_seconds']??'15'),
            'data-show-online'=>(string)($s['show_online']??'1'),
            'data-show-today'=>(string)($s['show_today']??'1'),
            'data-show-total'=>(string)($s['show_total']??'1'),
            'data-show-pageviews'=>(string)($s['show_pageviews']??'1'),
            'data-label'=>(string)($s['label']??'Visitantes'),
            'data-accent'=>(string)($s['accent']??'#d5a33d'),
            'data-floating-side'=>(string)($s['floating_side']??'right'),
            'data-online'=>(string)($stats['online']??0),
            'data-unique-today'=>(string)($stats['unique_today']??0),
            'data-unique-total'=>(string)($stats['unique_total']??0),
            'data-pageviews-total'=>(string)($stats['pageviews_total']??0),
            'data-pageviews-today'=>(string)($stats['pageviews_today']??0),
        ];

        $html='<link rel="stylesheet" href="/module-assets/live-traffic-counter-pro/assets/live-traffic.css?v=100">';
        $html.='<script id="omega-live-traffic" defer src="/module-assets/live-traffic-counter-pro/assets/live-traffic.js?v=100"';
        foreach($attrs as $k=>$v) $html.=' '.$k.'="'.$e($v).'"';
        $html.='></script>';
        return $html;
    }

    if($hook==='public.api'){
        $action=(string)($payload['action']??'');
        if($action==='stats'){
            $service->heartbeat();
            return ['ok'=>true,'stats'=>$service->stats()];
        }
        return null;
    }

    if($hook==='admin.action'){
        $action=(string)($payload['action']??'');
        $data=(array)($payload['data']??[]);

        if($action==='save_settings'){
            $service->save($data);
            return 'Live Traffic & Visitor Counter PRO actualizado.';
        }
        if($action==='reset_stats'){
            $service->reset();
            return 'Estadísticas de tráfico reiniciadas.';
        }
        if($action==='runtime_check'){
            $stats=$service->stats();
            return 'Runtime activo · Online: '.(int)$stats['online'].' · Visitantes únicos: '.(int)$stats['unique_total'].' · Pageviews: '.(int)$stats['pageviews_total'].'.';
        }
        return null;
    }

    if($hook==='admin.panel'){
        $s=$service->settings();
        $stats=$service->stats();
        $token=$e((string)($payload['csrf_token']??''));
        $checked=static fn(string $key,bool $default=false):string =>
            (($s[$key]??($default?'1':'0'))==='1')?' checked':'';

        ob_start(); ?>
<style>
.lt-grid{display:grid;grid-template-columns:minmax(0,1.05fr) minmax(320px,.85fr);gap:14px}
.lt-box{background:#11171e;border:1px solid #293440;border-radius:10px;padding:16px}
.lt-box h3{font:20px Georgia,serif;color:#f1cf78;margin:0 0 12px}
.lt-note{padding:11px 13px;border:1px solid #314153;border-radius:7px;background:#0c131a;color:#9eacba;margin-bottom:12px;line-height:1.5}
.lt-form{display:grid;gap:10px}
.lt-form label{font-size:10px;color:#a8b5c1}
.lt-form input[type=text],.lt-form input[type=number],.lt-form select{width:100%;margin-top:5px;background:#0a1016;color:#e5edf4;border:1px solid #344153;border-radius:6px;padding:9px}
.lt-form input[type=color]{width:100%;height:38px;margin-top:5px;padding:2px;border:1px solid #344153;background:#0a1016;border-radius:6px}
.lt-check{display:flex;gap:8px;align-items:center;padding:4px 0;color:#d5dee7!important}
.lt-two{display:grid;grid-template-columns:1fr 1fr;gap:10px}
.lt-stats{display:grid;grid-template-columns:1fr 1fr;gap:8px}
.lt-stat{padding:13px;border:1px solid #2d3c48;border-radius:8px;background:#0a1118}
.lt-stat span{display:block;color:#6f8192;font-size:8px;text-transform:uppercase;letter-spacing:.08em}
.lt-stat strong{display:block;margin-top:4px;color:#ecce81;font-size:22px}
.lt-preview{padding:14px;border:1px solid #3a4652;border-radius:10px;background:#0b1117}
.lt-preview-row{display:flex;gap:8px;flex-wrap:wrap}
.lt-preview-pill{padding:8px 10px;border:1px solid #3b4753;border-radius:7px;background:#111a22;color:#bdc9d3;font-size:10px}
.lt-preview-pill b{color:#eccb78}
.lt-actions{display:flex;gap:8px;flex-wrap:wrap}
@media(max-width:900px){.lt-grid{grid-template-columns:1fr}.lt-two{grid-template-columns:1fr}}
</style>

<div class="lt-grid">
<section class="lt-box">
<h3>◉ Live Traffic & Visitor Counter PRO</h3>
<div class="lt-note">
Contador nativo de OMEGA CMS. No envía IPs a servicios externos y actualiza las métricas visibles mediante la API del módulo.
</div>

<form method="post" action="/admin/modules/action" class="lt-form">
<input type="hidden" name="_csrf" value="<?= $token ?>">
<input type="hidden" name="slug" value="live-traffic-counter-pro">
<input type="hidden" name="module_action" value="save_settings">

<label class="lt-check"><input type="checkbox" name="enabled" value="1"<?= $checked('enabled',true) ?>> Activar seguimiento y contador público</label>
<label class="lt-check"><input type="checkbox" name="exclude_bots" value="1"<?= $checked('exclude_bots',true) ?>> Ignorar bots/crawlers conocidos</label>

<div class="lt-two">
<label>Tipo de contador
<select name="widget_type">
<?php foreach([
'online'=>'Solo visitantes online',
'total'=>'Visitantes únicos totales',
'today'=>'Visitantes de hoy',
'pageviews'=>'Pageviews totales',
'summary'=>'Tarjeta resumen',
'compact'=>'Contador compacto',
'bar'=>'Barra horizontal',
'floating'=>'Pestaña flotante'
] as $v=>$label): ?>
<option value="<?= $e($v) ?>" <?= (($s['widget_type']??'summary')===$v?'selected':'') ?>><?= $e($label) ?></option>
<?php endforeach; ?>
</select>
</label>

<label>Ubicación
<select name="placement">
<?php foreach([
'footer'=>'Footer',
'content_top'=>'Antes del contenido',
'content_bottom'=>'Después del contenido',
'floating'=>'Flotante'
] as $v=>$label): ?>
<option value="<?= $e($v) ?>" <?= (($s['placement']??'footer')===$v?'selected':'') ?>><?= $e($label) ?></option>
<?php endforeach; ?>
</select>
</label>
</div>

<div class="lt-two">
<label>Título
<input type="text" name="label" maxlength="60" value="<?= $e((string)($s['label']??'Visitantes')) ?>">
</label>
<label>Color de acento
<input type="color" name="accent" value="<?= $e((string)($s['accent']??'#d5a33d')) ?>">
</label>
</div>

<div class="lt-two">
<label>Ventana “online” (minutos)
<input type="number" name="online_window" min="1" max="60" value="<?= (int)($s['online_window']??5) ?>">
</label>
<label>Actualizar cada (segundos)
<input type="number" name="refresh_seconds" min="5" max="120" value="<?= (int)($s['refresh_seconds']??15) ?>">
</label>
</div>

<label>Lado de pestaña flotante
<select name="floating_side">
<option value="right" <?= (($s['floating_side']??'right')==='right'?'selected':'') ?>>Derecha</option>
<option value="left" <?= (($s['floating_side']??'right')==='left'?'selected':'') ?>>Izquierda</option>
</select>
</label>

<h3 style="margin-top:8px">Métricas visibles</h3>
<label class="lt-check"><input type="checkbox" name="show_online" value="1"<?= $checked('show_online',true) ?>> Visitantes online</label>
<label class="lt-check"><input type="checkbox" name="show_today" value="1"<?= $checked('show_today',true) ?>> Visitantes únicos hoy</label>
<label class="lt-check"><input type="checkbox" name="show_total" value="1"<?= $checked('show_total',true) ?>> Visitantes únicos totales</label>
<label class="lt-check"><input type="checkbox" name="show_pageviews" value="1"<?= $checked('show_pageviews',true) ?>> Pageviews</label>

<button class="btn btn-primary" type="submit">Guardar configuración</button>
</form>
</section>

<aside>
<section class="lt-box">
<h3>Tráfico actual</h3>
<div class="lt-stats">
<div class="lt-stat"><span>Online</span><strong><?= number_format((int)$stats['online']) ?></strong></div>
<div class="lt-stat"><span>Únicos hoy</span><strong><?= number_format((int)$stats['unique_today']) ?></strong></div>
<div class="lt-stat"><span>Únicos total</span><strong><?= number_format((int)$stats['unique_total']) ?></strong></div>
<div class="lt-stat"><span>Pageviews</span><strong><?= number_format((int)$stats['pageviews_total']) ?></strong></div>
<div class="lt-stat"><span>Desktop online</span><strong><?= number_format((int)($stats['devices']['desktop']??0)) ?></strong></div>
<div class="lt-stat"><span>Mobile online</span><strong><?= number_format((int)($stats['devices']['mobile']??0)) ?></strong></div>
</div>
</section>

<section class="lt-box" style="margin-top:14px">
<h3>Vista conceptual</h3>
<div class="lt-preview">
<div style="font-size:9px;color:#758697;margin-bottom:8px;text-transform:uppercase"><?= $e((string)($s['label']??'Visitantes')) ?></div>
<div class="lt-preview-row">
<div class="lt-preview-pill">● Online <b><?= (int)$stats['online'] ?></b></div>
<div class="lt-preview-pill">Hoy <b><?= (int)$stats['unique_today'] ?></b></div>
<div class="lt-preview-pill">Total <b><?= (int)$stats['unique_total'] ?></b></div>
<div class="lt-preview-pill">Views <b><?= (int)$stats['pageviews_total'] ?></b></div>
</div>
</div>
</section>

<section class="lt-box" style="margin-top:14px">
<h3>Mantenimiento</h3>
<div class="lt-actions">
<form method="post" action="/admin/modules/action">
<input type="hidden" name="_csrf" value="<?= $token ?>">
<input type="hidden" name="slug" value="live-traffic-counter-pro">
<input type="hidden" name="module_action" value="runtime_check">
<button class="btn btn-ghost" type="submit">Diagnóstico</button>
</form>
<form method="post" action="/admin/modules/action" onsubmit="return confirm('¿Reiniciar todas las estadísticas de visitas?');">
<input type="hidden" name="_csrf" value="<?= $token ?>">
<input type="hidden" name="slug" value="live-traffic-counter-pro">
<input type="hidden" name="module_action" value="reset_stats">
<button class="btn btn-ghost" type="submit">Reiniciar estadísticas</button>
</form>
</div>
</section>
</aside>
</div>
<?php
        return (string)ob_get_clean();
    }

    return null;
};
