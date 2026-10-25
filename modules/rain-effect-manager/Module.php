<?php
declare(strict_types=1);

require_once __DIR__.'/RainEffectService.php';

return static function(string $hook,array $payload,array $config,array $context): mixed {
    /** @var \Omega\Core\Database $cmsDb */
    $cmsDb=$context['cms_db'];
    $service=new OmegaRainEffectService($cmsDb);
    $e=static fn(string $v):string=>htmlspecialchars($v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');

    if($hook==='site.head'){
        $s=$service->settings();

        if(($s['enabled']??'0')!=='1') return null;
        if($service->disabledInAdmin()) return null;

        $cfg=$service->clientConfig();

        $attrs=[
            'data-enabled'=>$cfg['enabled']?'1':'0',
            'data-effect-type'=>(string)$cfg['effectType'],
            'data-placement-mode'=>(string)$cfg['placementMode'],
            'data-target-selector'=>(string)$cfg['targetSelector'],
            'data-z-index'=>(string)$cfg['zIndex'],
            'data-density'=>(string)$cfg['density'],
            'data-speed-min'=>(string)$cfg['speedMin'],
            'data-speed-max'=>(string)$cfg['speedMax'],
            'data-drop-length-min'=>(string)$cfg['dropLengthMin'],
            'data-drop-length-max'=>(string)$cfg['dropLengthMax'],
            'data-drop-width'=>(string)$cfg['dropWidth'],
            'data-wind'=>(string)$cfg['wind'],
            'data-opacity'=>(string)$cfg['opacity'],
            'data-color'=>(string)$cfg['color'],
            'data-splash-enabled'=>$cfg['splashEnabled']?'1':'0',
            'data-splash-density'=>(string)$cfg['splashDensity'],
            'data-splash-color'=>(string)$cfg['splashColor'],
            'data-lightning-enabled'=>$cfg['lightningEnabled']?'1':'0',
            'data-lightning-interval-min'=>(string)$cfg['lightningIntervalMin'],
            'data-lightning-interval-max'=>(string)$cfg['lightningIntervalMax'],
            'data-lightning-color'=>(string)$cfg['lightningColor'],
            'data-audio-enabled'=>$cfg['audioEnabled']?'1':'0',
            'data-audio-url'=>(string)$cfg['audioUrl'],
            'data-audio-volume'=>(string)$cfg['audioVolume'],
            'data-audio-loop'=>$cfg['audioLoop']?'1':'0',
            'data-pause-when-hidden'=>$cfg['pauseWhenHidden']?'1':'0',
            'data-respect-reduced-motion'=>$cfg['respectReducedMotion']?'1':'0',
            'data-disable-mobile'=>$cfg['disableMobile']?'1':'0',
            'data-mobile-density'=>(string)$cfg['mobileDensity'],
            'data-max-fps'=>(string)$cfg['maxFps'],
            'data-debug-mode'=>$cfg['debugMode']?'1':'0',
        ];

        $css='<link rel="stylesheet" href="/module-assets/rain-effect-manager/assets/css/raineffect.css?v=100">';

        $js='<script id="omega-rain-effect-script" defer src="/module-assets/rain-effect-manager/assets/js/raineffect.js?v=100"';
        foreach($attrs as $key=>$value){
            $js.=' '.$key.'="'.$e($value).'"';
        }
        $js.='></script>';

        return $css.$js;
    }

    if($hook==='admin.action'){
        $action=(string)($payload['action']??'');
        $data=(array)($payload['data']??[]);

        if($action==='save_settings'){
            $service->save($data);
            return 'Configuración de lluvia guardada correctamente.';
        }

        if($action==='runtime_check'){
            $s=$service->settings();
            return 'Runtime Rain Effect Manager Professional v1.0.0 activo. Estado interno: '.
                (($s['enabled']??'0')==='1'?'ACTIVO':'DESACTIVADO').'.';
        }

        if($action==='reset_defaults'){
            $service->resetDefaults();
            return 'Configuración de lluvia restaurada a valores recomendados.';
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
.re-grid{display:grid;grid-template-columns:minmax(0,1fr) minmax(380px,.92fr);gap:14px}
.re-box{background:#11171e;border:1px solid #293440;border-radius:10px;padding:16px}
.re-box h3{font:20px Georgia,serif;color:#f1cf78;margin:0 0 12px}
.re-note{padding:11px 13px;border:1px solid #314153;border-radius:7px;background:#0c131a;color:#9eacba;margin-bottom:12px;line-height:1.5}
.re-form{display:grid;gap:10px}
.re-form label{font-size:10px;color:#a8b5c1}
.re-form input[type=text],.re-form input[type=url],.re-form input[type=number],.re-form select{
width:100%;margin-top:5px;background:#0a1016;color:#e5edf4;border:1px solid #344153;border-radius:6px;padding:9px}
.re-check{display:flex;gap:8px;align-items:center;padding:4px 0;color:#d5dee7!important}
.re-two{display:grid;grid-template-columns:1fr 1fr;gap:10px}
.re-three{display:grid;grid-template-columns:repeat(3,1fr);gap:10px}
.re-color{display:grid;grid-template-columns:1fr 70px;gap:8px;align-items:end}
.re-color input[type=color]{width:70px;height:38px;padding:2px;border:1px solid #344153;background:#0a1016;border-radius:6px}
.re-preview{height:190px;position:relative;overflow:hidden;border:1px solid #324151;border-radius:8px;background:linear-gradient(#172638,#0b1017)}
.re-preview:before{content:"";position:absolute;inset:-40px;background:repeating-linear-gradient(105deg,transparent 0 15px,rgba(174,220,255,.45) 16px 17px,transparent 18px 32px);animation:reRain 1.1s linear infinite}
.re-preview:after{content:"OMEGA MU · RAIN EFFECT";position:absolute;left:15px;bottom:12px;color:#e6edf3;font-size:11px;letter-spacing:1.4px}
@keyframes reRain{to{transform:translate(20px,80px)}}
.re-preset{display:grid;gap:8px}
.re-preset div{padding:9px;border:1px solid #293744;border-radius:6px;background:#0b1117;color:#9dacb9}
.re-warn{color:#d8b567;font-size:10px;line-height:1.5}
@media(max-width:950px){.re-grid{grid-template-columns:1fr}.re-two,.re-three{grid-template-columns:1fr}}
</style>

<div class="re-grid">
<section class="re-box">
<h3>🌧 Rain Effect Manager Professional</h3>
<div class="re-note">
Efecto visual de lluvia por Canvas. No utiliza jQuery, no bloquea clics y puede pausarse automáticamente cuando la pestaña queda oculta.
</div>

<form method="post" action="/admin/modules/action" class="re-form">
<input type="hidden" name="_csrf" value="<?= $token ?>">
<input type="hidden" name="slug" value="rain-effect-manager">
<input type="hidden" name="module_action" value="save_settings">

<label class="re-check"><input type="checkbox" name="enabled" value="1"<?= $checked('enabled',true) ?>> Efecto activo</label>
<label class="re-check"><input type="checkbox" name="disable_admincp" value="1"<?= $checked('disable_admincp',true) ?>> No mostrar dentro del AdminCP</label>

<div class="re-two">
<label>Tipo de efecto
<select name="effect_type">
<?php foreach(['drizzle'=>'Llovizna','rain'=>'Lluvia','storm'=>'Tormenta'] as $v=>$label): ?>
<option value="<?= $e($v) ?>" <?= (($s['effect_type']??'rain')===$v?'selected':'') ?>><?= $e($label) ?></option>
<?php endforeach; ?>
</select>
</label>

<label>Área del efecto
<select name="placement_mode">
<option value="viewport" <?= (($s['placement_mode']??'viewport')==='viewport'?'selected':'') ?>>Toda la pantalla</option>
<option value="element" <?= (($s['placement_mode']??'viewport')==='element'?'selected':'') ?>>Elemento CSS</option>
</select>
</label>
</div>

<label>Selector CSS del área
<input type="text" name="target_selector" maxlength="180" value="<?= $e((string)($s['target_selector']??'body')) ?>" placeholder="body">
</label>

<div class="re-three">
<label>Z-Index<input type="number" name="z_index" min="1" max="999999" value="<?= (int)($s['z_index']??9990) ?>"></label>
<label>Densidad<input type="number" name="density" min="5" max="500" value="<?= (int)($s['density']??110) ?>"></label>
<label>FPS máximo<input type="number" name="max_fps" min="15" max="120" value="<?= (int)($s['max_fps']??60) ?>"></label>
</div>

<h3 style="margin-top:10px">Gotas</h3>

<div class="re-two">
<label>Velocidad mínima (ms)<input type="number" name="speed_min" min="100" max="5000" value="<?= (int)($s['speed_min']??650) ?>"></label>
<label>Velocidad máxima (ms)<input type="number" name="speed_max" min="100" max="8000" value="<?= (int)($s['speed_max']??1200) ?>"></label>
<label>Largo mínimo<input type="number" name="drop_length_min" min="2" max="100" value="<?= (int)($s['drop_length_min']??14) ?>"></label>
<label>Largo máximo<input type="number" name="drop_length_max" min="2" max="160" value="<?= (int)($s['drop_length_max']??30) ?>"></label>
<label>Ancho<input type="number" step="0.1" name="drop_width" min="0.5" max="8" value="<?= $e((string)($s['drop_width']??'1')) ?>"></label>
<label>Viento<input type="number" name="wind" min="-100" max="100" value="<?= (int)($s['wind']??-8) ?>"></label>
<label>Opacidad<input type="number" step="0.05" name="opacity" min="0.05" max="1" value="<?= $e((string)($s['opacity']??'0.65')) ?>"></label>
<label>Color<input type="color" name="color" value="<?= $e((string)($s['color']??'#a8d8ff')) ?>"></label>
</div>

<h3 style="margin-top:10px">Salpicaduras y relámpagos</h3>
<label class="re-check"><input type="checkbox" name="splash_enabled" value="1"<?= $checked('splash_enabled',true) ?>> Mostrar salpicaduras</label>
<label class="re-check"><input type="checkbox" name="lightning_enabled" value="1"<?= $checked('lightning_enabled') ?>> Activar relámpagos</label>

<div class="re-two">
<label>Cantidad salpicaduras<input type="number" name="splash_density" min="0" max="200" value="<?= (int)($s['splash_density']??35) ?>"></label>
<label>Color salpicadura<input type="color" name="splash_color" value="<?= $e((string)($s['splash_color']??'#d9f1ff')) ?>"></label>
<label>Relámpago mínimo (s)<input type="number" name="lightning_interval_min" min="2" max="600" value="<?= (int)($s['lightning_interval_min']??12) ?>"></label>
<label>Relámpago máximo (s)<input type="number" name="lightning_interval_max" min="2" max="900" value="<?= (int)($s['lightning_interval_max']??30) ?>"></label>
<label>Color relámpago<input type="color" name="lightning_color" value="<?= $e((string)($s['lightning_color']??'#ffffff')) ?>"></label>
</div>

<h3 style="margin-top:10px">Rendimiento</h3>
<label class="re-check"><input type="checkbox" name="pause_when_hidden" value="1"<?= $checked('pause_when_hidden',true) ?>> Pausar cuando la pestaña está oculta</label>
<label class="re-check"><input type="checkbox" name="respect_reduced_motion" value="1"<?= $checked('respect_reduced_motion',true) ?>> Respetar “Reduced Motion” del navegador</label>
<label class="re-check"><input type="checkbox" name="disable_mobile" value="1"<?= $checked('disable_mobile') ?>> Desactivar efecto en móviles</label>
<label>Densidad móvil<input type="number" name="mobile_density" min="5" max="250" value="<?= (int)($s['mobile_density']??45) ?>"></label>

<h3 style="margin-top:10px">Sonido ambiental</h3>
<label class="re-check"><input type="checkbox" name="audio_enabled" value="1"<?= $checked('audio_enabled') ?>> Activar sonido de lluvia</label>
<label class="re-check"><input type="checkbox" name="audio_loop" value="1"<?= $checked('audio_loop',true) ?>> Repetir sonido</label>
<label>Archivo de audio
<input type="text" name="audio_url" maxlength="700" value="<?= $e((string)($s['audio_url']??'')) ?>" placeholder="/public/assets/audio/rain.mp3">
</label>
<label>Volumen 0–1
<input type="number" step="0.05" name="audio_volume" min="0" max="1" value="<?= $e((string)($s['audio_volume']??'0.25')) ?>">
</label>
<div class="re-warn">
Para máxima compatibilidad con la CSP de Omega usa un archivo alojado en tu propio dominio, por ejemplo <code>/public/assets/audio/rain.mp3</code>.
</div>

<label class="re-check"><input type="checkbox" name="debug_mode" value="1"<?= $checked('debug_mode') ?>> Modo diagnóstico en consola</label>

<button class="btn btn-primary" type="submit">Guardar configuración de lluvia</button>
</form>
</section>

<aside>
<section class="re-box">
<h3>Vista conceptual</h3>
<div class="re-preview"></div>
</section>

<section class="re-box" style="margin-top:14px">
<h3>Configuraciones recomendadas</h3>
<div class="re-preset">
<div><strong>Llovizna:</strong> 45 gotas · 1300–2200 ms · opacidad 0.35.</div>
<div><strong>Lluvia normal:</strong> 110 gotas · 650–1200 ms · opacidad 0.65.</div>
<div><strong>Tormenta:</strong> 220 gotas · 350–850 ms · relámpagos activos.</div>
<div><strong>OmegaCMS:</strong> área “Toda la pantalla” · selector <code>body</code>.</div>
</div>
</section>
</aside>
</div>
        <?php
        return (string)ob_get_clean();
    }

    return null;
};
