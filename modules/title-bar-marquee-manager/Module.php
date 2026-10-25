<?php
declare(strict_types=1);

require_once __DIR__.'/TitleMarqueeService.php';

return static function(string $hook,array $payload,array $config,array $context): mixed {
    /** @var \Omega\Core\Database $cmsDb */
    $cmsDb=$context['cms_db'];
    $service=new OmegaTitleMarqueeService($cmsDb);
    $e=static fn(string $v):string=>htmlspecialchars($v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');

    if($hook==='site.head'){
        $s=$service->settings();

        if(($s['enabled']??'0')!=='1') return null;
        if($service->disabledInAdmin()) return null;

        $cfg=$service->clientConfig();

        $attrs=[
            'data-enabled'=>$cfg['enabled']?'1':'0',
            'data-use-original-title'=>$cfg['useOriginalTitle']?'1':'0',
            'data-custom-text'=>(string)$cfg['customText'],
            'data-prefix'=>(string)$cfg['prefix'],
            'data-suffix'=>(string)$cfg['suffix'],
            'data-separator'=>(string)$cfg['separator'],
            'data-speed'=>(string)$cfg['speed'],
            'data-direction'=>(string)$cfg['direction'],
            'data-mode'=>(string)$cfg['mode'],
            'data-padding-spaces'=>(string)$cfg['paddingSpaces'],
            'data-pause-on-blur'=>$cfg['pauseOnBlur']?'1':'0',
            'data-restore-on-blur'=>$cfg['restoreOnBlur']?'1':'0',
            'data-restore-on-hover'=>$cfg['restoreOnHover']?'1':'0',
            'data-start-delay'=>(string)$cfg['startDelay'],
            'data-stop-after-cycles'=>(string)$cfg['stopAfterCycles'],
            'data-preserve-module-title'=>$cfg['preserveModuleTitle']?'1':'0',
            'data-trim-whitespace'=>$cfg['trimWhitespace']?'1':'0',
            'data-debug-mode'=>$cfg['debugMode']?'1':'0',
        ];

        $html='<script id="omega-title-marquee-script" defer src="/module-assets/title-bar-marquee-manager/assets/js/titlemarquee.js?v=100"';
        foreach($attrs as $key=>$value){
            $html.=' '.$key.'="'.$e($value).'"';
        }
        $html.='></script>';

        return $html;
    }

    if($hook==='admin.action'){
        $action=(string)($payload['action']??'');
        $data=(array)($payload['data']??[]);

        if($action==='save_settings'){
            $service->save($data);
            return 'Configuración de Title Bar Marquee guardada correctamente.';
        }

        if($action==='runtime_check'){
            $s=$service->settings();
            return 'Runtime Title Bar Marquee Manager Professional v1.0.1 activo. Estado interno: '.
                (($s['enabled']??'0')==='1'?'ACTIVO':'DESACTIVADO').'.';
        }

        if($action==='reset_defaults'){
            $service->resetDefaults();
            return 'Configuración restaurada a los valores recomendados.';
        }

        return null;
    }

    if($hook==='admin.panel'){
        $s=$service->settings();
        $token=$e((string)($payload['csrf_token']??''));

        $checked=static fn(string $key,bool $default=false):string =>
            (($s[$key]??($default?'1':'0'))==='1')?' checked':'';

        ob_start(); ?>
<style>
.tm-wrap{display:grid;grid-template-columns:minmax(0,1fr) minmax(360px,.85fr);gap:14px}
.tm-box{background:#11171e;border:1px solid #293440;border-radius:10px;padding:16px}
.tm-box h3{font:20px Georgia,serif;margin:0 0 12px;color:#f1cf78}
.tm-note{padding:11px 13px;border:1px solid #314153;border-radius:7px;background:#0c131a;color:#9eacba;margin-bottom:12px;line-height:1.5}
.tm-form{display:grid;gap:11px}
.tm-form label{font-size:10px;color:#a8b5c1}
.tm-form input[type=text],.tm-form input[type=number],.tm-form textarea,.tm-form select{
width:100%;margin-top:5px;background:#0a1016;color:#e5edf4;border:1px solid #344153;border-radius:6px;padding:9px}
.tm-check{display:flex;gap:8px;align-items:center;padding:5px 0;color:#d5dee7!important}
.tm-grid2{display:grid;grid-template-columns:1fr 1fr;gap:10px}
.tm-preview{padding:16px;border:1px solid #354658;border-radius:8px;background:#090e14}
.tm-preview .tab{display:flex;align-items:center;gap:8px;background:#161d25;border-radius:7px;padding:10px 13px;color:#e8edf2}
.tm-preview .favicon{width:14px;height:14px;border-radius:3px;background:#d4a94d;box-shadow:0 0 10px rgba(212,169,77,.35)}
.tm-example{display:grid;gap:8px;margin-top:12px}
.tm-example code{display:block;padding:8px;border-radius:5px;background:#080d12;color:#d9c07c;white-space:normal}
.tm-save{width:100%;margin-top:4px}
@media(max-width:900px){.tm-wrap{grid-template-columns:1fr}.tm-grid2{grid-template-columns:1fr}}
</style>

<div class="tm-wrap">
<section class="tm-box">
<h3>📰 Title Bar Marquee Manager Professional</h3>
<div class="tm-note">
Anima exclusivamente el título de la pestaña del navegador. No modifica encabezados ni contenido visible de OmegaCMS y no utiliza jQuery.
</div>

<form method="post" action="/admin/modules/action" class="tm-form">
<input type="hidden" name="_csrf" value="<?= $token ?>">
<input type="hidden" name="slug" value="title-bar-marquee-manager">
<input type="hidden" name="module_action" value="save_settings">

<label class="tm-check"><input type="checkbox" name="enabled" value="1"<?= $checked('enabled',true) ?>> Plugin activo</label>
<label class="tm-check"><input type="checkbox" name="disable_admincp" value="1"<?= $checked('disable_admincp',true) ?>> No animar dentro del AdminCP</label>
<label class="tm-check"><input type="checkbox" name="use_original_title" value="1"<?= $checked('use_original_title',true) ?>> Usar el título actual generado por OmegaCMS</label>
<label class="tm-check"><input type="checkbox" name="preserve_module_title" value="1"<?= $checked('preserve_module_title',true) ?>> Conservar también el título de la página cuando uso texto personalizado</label>
<label class="tm-check"><input type="checkbox" name="trim_whitespace" value="1"<?= $checked('trim_whitespace',true) ?>> Limpiar espacios duplicados</label>
<label class="tm-check"><input type="checkbox" name="debug_mode" value="1"<?= $checked('debug_mode') ?>> Modo diagnóstico en consola</label>

<label>Texto personalizado
<textarea name="custom_text" rows="3" maxlength="500" placeholder="Bienvenido a Omega MU"><?= $e((string)($s['custom_text']??'')) ?></textarea>
</label>

<div class="tm-grid2">
<label>Prefijo
<input type="text" name="prefix" maxlength="120" value="<?= $e((string)($s['prefix']??'')) ?>" placeholder="⚔️ ">
</label>
<label>Sufijo
<input type="text" name="suffix" maxlength="160" value="<?= $e((string)($s['suffix']??'')) ?>" placeholder=" | Servidor Online">
</label>
</div>

<label>Separador de repetición
<input type="text" name="separator" maxlength="40" value="<?= $e((string)($s['separator']??'✦')) ?>" placeholder="✦">
</label>

<div class="tm-grid2">
<label>Modo
<select name="mode">
<?php foreach(['characters'=>'Caracteres','words'=>'Palabras','bounce'=>'Rebote'] as $value=>$label): ?>
<option value="<?= $e($value) ?>" <?= (($s['mode']??'characters')===$value?'selected':'') ?>><?= $e($label) ?></option>
<?php endforeach; ?>
</select>
</label>

<label>Dirección inicial
<select name="direction">
<option value="left" <?= (($s['direction']??'left')==='left'?'selected':'') ?>>Izquierda</option>
<option value="right" <?= (($s['direction']??'left')==='right'?'selected':'') ?>>Derecha</option>
</select>
</label>
</div>

<div class="tm-grid2">
<label>Velocidad (ms)
<input type="number" name="speed" min="40" max="5000" value="<?= (int)($s['speed']??180) ?>">
</label>
<label>Espacios entre repeticiones
<input type="number" name="padding_spaces" min="0" max="30" value="<?= (int)($s['padding_spaces']??3) ?>">
</label>
<label>Espera inicial (ms)
<input type="number" name="start_delay" min="0" max="30000" value="<?= (int)($s['start_delay']??700) ?>">
</label>
<label>Detener tras X ciclos
<input type="number" name="stop_after_cycles" min="0" max="9999" value="<?= (int)($s['stop_after_cycles']??0) ?>">
</label>
</div>

<label class="tm-check"><input type="checkbox" name="pause_on_blur" value="1"<?= $checked('pause_on_blur',true) ?>> Pausar cuando la pestaña pierde foco</label>
<label class="tm-check"><input type="checkbox" name="restore_on_blur" value="1"<?= $checked('restore_on_blur') ?>> Restaurar título original al perder foco</label>
<label class="tm-check"><input type="checkbox" name="restore_on_hover" value="1"<?= $checked('restore_on_hover') ?>> Restaurar título al mover el mouse sobre la página</label>

<button class="btn btn-primary tm-save" type="submit">Guardar configuración</button>
</form>
</section>

<aside>
<section class="tm-box">
<h3>Vista conceptual</h3>
<div class="tm-preview">
<div class="tab"><span class="favicon"></span><span><?= $e((string)(($s['prefix']??'').(($s['custom_text']??'')!==''?$s['custom_text']:'Omega MU').($s['suffix']??''))) ?></span></div>
</div>
<div class="tm-example">
<strong>Ejemplos</strong>
<code>⚔️ Omega MU Online ✦ Temporada activa ✦</code>
<code>🎃 Evento Halloween activo 👻</code>
<code>⚠️ Servidor en mantenimiento ⚠️</code>
</div>
</section>

<section class="tm-box" style="margin-top:14px">
<h3>Velocidades recomendadas</h3>
<p><strong>Suave:</strong> 220–300 ms</p>
<p><strong>Normal:</strong> 150–200 ms</p>
<p><strong>Rápido:</strong> 70–120 ms</p>
<p><strong>Palabras:</strong> 350–650 ms</p>
<p><strong>Rebote:</strong> 180–260 ms</p>
</section>
</aside>
</div>
        <?php
        return (string)ob_get_clean();
    }

    return null;
};
