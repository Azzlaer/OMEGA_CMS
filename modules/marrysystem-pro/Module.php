<?php
declare(strict_types=1);

require_once __DIR__.'/MarrySystemSettings.php';
require_once __DIR__.'/MarrySystemService.php';

return static function(string $hook,array $payload,array $config,array $context): mixed {
    /** @var \Omega\Core\Database $cmsDb */
    $cmsDb=$context['cms_db'];
    /** @var \Omega\Core\Database $muDb */
    $muDb=$context['mu_db'];
    $root=(string)$context['root'];

    $settingsService=new OmegaMarrySystemSettings($cmsDb,$root);
    $service=new OmegaMarrySystemService($muDb);

    $e=static fn(mixed $v): string =>
        htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');

    $styles=static function(): string {
        return <<<'CSS'
<style>
.marrypro{--pink:#f19ac3;--rose:#c35d88;--gold:#f0c878;--panel:#17121a;--border:#483547;--muted:#aa97aa;color:#eee}
.marrypro *{box-sizing:border-box}
.marrypro a{text-decoration:none}
.marrypro .marry-hero{position:relative;overflow:hidden;border:1px solid #563b55;border-radius:14px;padding:22px 24px;background:radial-gradient(circle at 82% 20%,rgba(241,154,195,.18),transparent 34%),radial-gradient(circle at 18% 75%,rgba(240,200,120,.08),transparent 30%),linear-gradient(135deg,#17101a,#261423 54%,#110d12);margin-bottom:16px}
.marrypro .marry-hero:before{content:"♥";position:absolute;right:34px;top:-34px;font-size:128px;font-weight:900;color:rgba(255,255,255,.04);pointer-events:none}
.marrypro .eyebrow{font-size:10px;letter-spacing:.16em;color:#c7a5bd;font-weight:900;text-transform:uppercase}
.marrypro .marry-hero h2{position:relative;font-size:25px;line-height:1.2;margin:5px 0 7px;color:#ffd7e9}
.marrypro .marry-hero p{position:relative;max-width:760px;margin:0;line-height:1.55;color:#baa8b7}
.marrypro .marry-banner{margin-bottom:16px;border:1px solid #563b55;border-radius:14px;overflow:hidden;background:#100b10}
.marrypro .marry-banner img{display:block;width:100%}
.marrypro .summary{display:grid;grid-template-columns:repeat(4,minmax(120px,1fr));gap:9px;margin:0 0 16px}
.marrypro .kpi{background:linear-gradient(180deg,#211823,#151016);border:1px solid #483547;border-radius:10px;padding:12px}
.marrypro .kpi span{display:block;font-size:9px;text-transform:uppercase;letter-spacing:.08em;color:#a98ea3}
.marrypro .kpi strong{display:block;margin-top:4px;font-size:22px;color:#ffd7e9}
.marrypro .grid{display:grid;grid-template-columns:minmax(0,1.35fr) minmax(300px,.65fr);gap:14px}
.marrypro .panel{background:linear-gradient(145deg,#191219,#100d11);border:1px solid #483547;border-radius:11px;padding:15px}
.marrypro .panel h3{margin:2px 0 10px;color:#f1cae0}
.marrypro .muted{color:var(--muted)}
.marrypro .couples{display:grid;gap:8px}
.marrypro .couple{display:grid;grid-template-columns:1fr auto 1fr auto;gap:10px;align-items:center;padding:11px;border:1px solid #3c2d3b;border-radius:9px;background:#120e13}
.marrypro .name{font-weight:900;color:#f3d9e7}
.marrypro .heart{font-size:18px;color:#ef7fb0}
.marrypro .date{font-size:11px;color:#8f7d8d;text-align:right}
.marrypro .history{display:grid;gap:7px}
.marrypro .history-row{display:grid;grid-template-columns:140px 120px minmax(170px,1fr);gap:9px;padding:9px;border-bottom:1px solid #2f242f}
.marrypro .badge{display:inline-flex;padding:3px 7px;border-radius:999px;border:1px solid #594159;background:#1a121a;font-size:9px;font-weight:900}
.marrypro .badge.ok{color:#94dfb3;border-color:#315443}.marrypro .badge.warn{color:#f4c77c;border-color:#59472f}.marrypro .badge.off{color:#d8a1a1;border-color:#593738}
.marrypro .admin-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}
.marrypro .field{display:grid;gap:6px}
.marrypro .field input,.marrypro .field select,.marrypro .field textarea{width:100%;padding:10px 11px;border:1px solid #553c54;border-radius:8px;background:#110d12;color:#f3ebf2}
.marrypro .field textarea{min-height:88px;resize:vertical}
.marrypro .choice{display:grid;grid-template-columns:repeat(3,1fr);gap:8px}
.marrypro .choice label{display:flex;gap:8px;align-items:center;border:1px solid #4d394c;border-radius:9px;padding:10px;background:#130f14}
.marrypro .preview{max-width:100%;border-radius:10px;border:1px solid #553c54;display:block}
.marrypro .actions{display:flex;gap:8px;flex-wrap:wrap;margin-top:12px}
.marrypro .empty{padding:20px;text-align:center;border:1px dashed #5c465b;border-radius:9px;color:#aa96a8}
.marrypro .mine{box-shadow:inset 3px 0 0 #e79abc;background:rgba(231,154,188,.04)}
@media(max-width:900px){.marrypro .summary{grid-template-columns:1fr 1fr}.marrypro .grid{grid-template-columns:1fr}}
@media(max-width:720px){.marrypro .admin-grid{grid-template-columns:1fr}.marrypro .choice{grid-template-columns:1fr}.marrypro .couple{grid-template-columns:1fr}.marrypro .heart{display:none}.marrypro .history-row{grid-template-columns:1fr}.marrypro .marry-hero:before{font-size:76px}}
</style>
CSS;
    };

    $headerHtml=static function(array $ui) use($e): string {
        if($ui['header_mode']==='banner' && $ui['banner_path']!==''){
            return '<div class="marry-banner" style="height:'.(int)$ui['banner_height'].'px"><img src="'.$e($ui['banner_path']).'" alt="'.$e($ui['banner_alt']).'" style="height:100%;object-fit:'.$e($ui['banner_fit']).';object-position:'.$e($ui['banner_position']).'"></div>';
        }

        if($ui['header_mode']==='design'){
            return '<div class="marry-hero">
                <span class="eyebrow">'.$e($ui['design_eyebrow']).'</span>
                <h2>'.$e($ui['design_title']).'</h2>
                <p>'.$e($ui['design_subtitle']).'</p>
            </div>';
        }

        return '';
    };

    if($hook==='admin.action'){
        $action=(string)($payload['action']??'');
        $data=(array)($payload['data']??[]);
        $files=(array)($payload['files']??[]);

        if($action==='save_settings'){
            $settingsService->save($data);
            return 'Configuración de MarrySystem guardada correctamente.';
        }

        if($action==='upload_banner'){
            $settingsService->uploadBanner((array)($files['banner_image']??[]));
            return 'Banner de MarrySystem subido y activado correctamente.';
        }

        if($action==='delete_banner'){
            $settingsService->deleteBanner();
            return 'Banner de MarrySystem eliminado. Se restauró el diseño.';
        }

        throw new RuntimeException('Acción MarrySystem no reconocida.');
    }

    if($hook==='admin.panel'){
        $ui=$settingsService->get();
        $token=$e((string)($payload['csrf_token']??''));
        $diag=$service->diagnostics();

        $cards='';
        foreach($diag as $table=>$d){
            $ok=$d['exists'] && !$d['missing'];
            $cards.='<div class="kpi"><span>'.$e($table).'</span><strong class="'.($ok?'':'muted').'">'.($ok?'ONLINE':'REVISAR').'</strong><small class="muted">'.number_format((int)$d['count'],0,',','.').' registros</small></div>';
        }

        $bannerPreview=$ui['banner_path']!==''
            ? '<img class="preview" src="'.$e($ui['banner_path']).'" alt="'.$e($ui['banner_alt']).'">'
            : '<div class="empty">No hay banner personalizado cargado.</div>';

        return $styles().'<div class="marrypro">
          <div class="panel">
            <span class="eyebrow">APARIENCIA</span>
            <h3>Cabecera del Sistema de Casamientos</h3>
            <p class="muted">Elige un solo modo. Diseño y banner nunca se muestran juntos.</p>

            <form method="post" action="/admin/modules/action">
              <input type="hidden" name="_csrf" value="'.$token.'">
              <input type="hidden" name="slug" value="marrysystem-pro">
              <input type="hidden" name="module_action" value="save_settings">

              <div class="choice">
                <label><input type="radio" name="header_mode" value="design" '.($ui['header_mode']==='design'?'checked':'').'> Diseño MarrySystem</label>
                <label><input type="radio" name="header_mode" value="banner" '.($ui['header_mode']==='banner'?'checked':'').'> Banner personalizado</label>
                <label><input type="radio" name="header_mode" value="none" '.($ui['header_mode']==='none'?'checked':'').'> Sin cabecera</label>
              </div>

              <div class="admin-grid" style="margin-top:14px">
                <label class="field">Eyebrow
                  <input name="design_eyebrow" maxlength="120" value="'.$e($ui['design_eyebrow']).'">
                </label>
                <label class="field">Título
                  <input name="design_title" maxlength="180" value="'.$e($ui['design_title']).'">
                </label>
                <label class="field" style="grid-column:1/-1">Descripción
                  <textarea name="design_subtitle" maxlength="500">'.$e($ui['design_subtitle']).'</textarea>
                </label>
                <label class="field">Texto alternativo
                  <input name="banner_alt" maxlength="180" value="'.$e($ui['banner_alt']).'">
                </label>
                <label class="field">Ajuste de imagen
                  <select name="banner_fit">
                    <option value="cover" '.($ui['banner_fit']==='cover'?'selected':'').'>Rellenar (cover)</option>
                    <option value="contain" '.($ui['banner_fit']==='contain'?'selected':'').'>Encajar (contain)</option>
                    <option value="fill" '.($ui['banner_fit']==='fill'?'selected':'').'>Estirar (fill)</option>
                    <option value="scale-down" '.($ui['banner_fit']==='scale-down'?'selected':'').'>Tamaño natural (scale-down)</option>
                  </select>
                </label>
                <label class="field">Posición
                  <select name="banner_position">
                    <option value="center" '.($ui['banner_position']==='center'?'selected':'').'>Centro</option>
                    <option value="top" '.($ui['banner_position']==='top'?'selected':'').'>Arriba</option>
                    <option value="bottom" '.($ui['banner_position']==='bottom'?'selected':'').'>Abajo</option>
                    <option value="left" '.($ui['banner_position']==='left'?'selected':'').'>Izquierda</option>
                    <option value="right" '.($ui['banner_position']==='right'?'selected':'').'>Derecha</option>
                  </select>
                </label>
                <label class="field">Altura del banner (px)
                  <input type="number" name="banner_height" min="160" max="720" value="'.(int)$ui['banner_height'].'">
                </label>
                <label class="field">Parejas a mostrar
                  <input type="number" name="couples_limit" min="10" max="200" value="'.(int)$ui['couples_limit'].'">
                </label>
                <label class="field">Historial a mostrar
                  <input type="number" name="history_limit" min="10" max="200" value="'.(int)$ui['history_limit'].'">
                </label>
              </div>

              <div class="choice" style="margin-top:14px">
                <label><input type="checkbox" name="show_summary" value="1" '.($ui['show_summary']?'checked':'').'> Mostrar resumen</label>
                <label><input type="checkbox" name="show_history" value="1" '.($ui['show_history']?'checked':'').'> Mostrar historial</label>
                <label><input type="checkbox" name="show_personal_status" value="1" '.($ui['show_personal_status']?'checked':'').'> Mostrar estado de mi cuenta</label>
              </div>

              <div class="actions">
                <button class="btn btn-primary" type="submit">Guardar configuración</button>
                <a class="btn" href="/rankings?type=marrysystem" target="_blank" rel="noopener">Vista previa</a>
              </div>
            </form>
          </div>

          <div class="panel" style="margin-top:14px">
            <span class="eyebrow">BANNER PERSONALIZADO</span>
            <h3>Imagen del Sistema de Casamientos</h3>
            <p class="muted">JPG, PNG o WEBP · máximo 8 MB · recomendado 1600×420.</p>
            '.$bannerPreview.'
            <div class="actions">
              <form method="post" action="/admin/modules/action" enctype="multipart/form-data">
                <input type="hidden" name="_csrf" value="'.$token.'">
                <input type="hidden" name="slug" value="marrysystem-pro">
                <input type="hidden" name="module_action" value="upload_banner">
                <input type="file" name="banner_image" accept="image/jpeg,image/png,image/webp" required>
                <button class="btn btn-primary" type="submit">Subir y usar banner</button>
              </form>'.
              ($ui['banner_path']!==''?'<form method="post" action="/admin/modules/action" onsubmit="return confirm(\'¿Eliminar el banner de MarrySystem?\')">
                <input type="hidden" name="_csrf" value="'.$token.'">
                <input type="hidden" name="slug" value="marrysystem-pro">
                <input type="hidden" name="module_action" value="delete_banner">
                <button class="btn btn-danger" type="submit">Eliminar banner</button>
              </form>':'').'
            </div>
          </div>

          <div class="panel" style="margin-top:14px">
            <span class="eyebrow">DIAGNÓSTICO</span>
            <h3>Tablas MarrySystem</h3>
            <div class="summary">'.$cards.'</div>
            <p class="muted">Las tablas del GameServer son solo lectura. OMEGA únicamente guarda configuración visual en su propia base CMS.</p>
          </div>
        </div>';
    }

    if($hook!=='ranking.page') return null;

    $ui=$settingsService->get();

    if(!$service->ready()){
        return [
            'available'=>false,
            'html'=>$styles().'<div class="marrypro"><div class="empty">MarrySystem no está disponible: faltan tablas o columnas requeridas.</div></div>'
        ];
    }

    $accountId=trim((string)($payload['account_id']??''));
    $summary=$service->summary();
    $couples=$service->activeCouples((int)$ui['couples_limit']);
    $history=$ui['show_history']?$service->recentHistory((int)$ui['history_limit']):[];
    $personal=($ui['show_personal_status'] && $accountId!=='')
        ? $service->personalStatus($accountId)
        : ['couples'=>[],'requests'=>[],'history'=>[]];

    $html=$styles().'<div class="marrypro">'.$headerHtml($ui);

    if($ui['show_summary']){
        $html.='<div class="summary">
          <div class="kpi"><span>Parejas activas</span><strong>'.number_format((int)$summary['active_couples'],0,',','.').'</strong></div>
          <div class="kpi"><span>Matrimonios</span><strong>'.number_format((int)$summary['total_marriages'],0,',','.').'</strong></div>
          <div class="kpi"><span>Divorcios</span><strong>'.number_format((int)$summary['divorced'],0,',','.').'</strong></div>
          <div class="kpi"><span>Solicitudes pendientes</span><strong>'.number_format((int)$summary['pending_requests'],0,',','.').'</strong></div>
        </div>';
    }

    if($ui['show_personal_status'] && $accountId!==''){
        $html.='<section class="panel" style="margin-bottom:14px">
          <span class="eyebrow">MI CUENTA</span><h3>Mi estado sentimental</h3>';

        if(!$personal['couples'] && !$personal['requests']){
            $html.='<p class="muted">No hay matrimonios ni solicitudes relacionadas con tus personajes.</p>';
        }

        foreach($personal['couples'] as $row){
            $active=!empty($row['IsActive']);
            $html.='<div class="couple mine">
              <div class="name">'.$e((string)$row['Character1']).'</div>
              <div class="heart">♥</div>
              <div class="name">'.$e((string)$row['Character2']).'</div>
              <div class="date"><span class="badge '.($active?'ok':'off').'">'.($active?'CASADOS':'FINALIZADO').'</span><br>'.$e((string)$row['MarriedDate']).'</div>
            </div>';
        }

        if($personal['requests']){
            $html.='<div class="history" style="margin-top:12px">';
            foreach($personal['requests'] as $req){
                $status=strtoupper((string)$req['Status']);
                $html.='<div class="history-row">
                  <div>'.$e((string)$req['CreatedDate']).'</div>
                  <div><span class="badge warn">'.$e($status).'</span></div>
                  <div><strong>'.$e((string)$req['RequesterName']).'</strong> → <strong>'.$e((string)$req['TargetName']).'</strong></div>
                </div>';
            }
            $html.='</div>';
        }

        $html.='</section>';
    }

    $html.='<div class="grid">
      <section class="panel"><span class="eyebrow">PAREJAS ACTIVAS</span><h3>💍 Casamientos del servidor</h3><div class="couples">';

    if(!$couples){
        $html.='<div class="empty">Todavía no hay parejas activas registradas.</div>';
    }else{
        foreach($couples as $row){
            $html.='<div class="couple">
              <div class="name"><a href="/character/'.rawurlencode((string)$row['Character1']).'">'.$e((string)$row['Character1']).'</a></div>
              <div class="heart">♥</div>
              <div class="name"><a href="/character/'.rawurlencode((string)$row['Character2']).'">'.$e((string)$row['Character2']).'</a></div>
              <div class="date">'.$e((string)$row['MarriedDate']).'</div>
            </div>';
        }
    }

    $html.='</div></section>';

    $html.='<aside class="panel"><span class="eyebrow">ESTADÍSTICAS</span><h3>Estado del sistema</h3>
      <div class="summary" style="grid-template-columns:1fr 1fr">
        <div class="kpi"><span>Solicitudes aprobadas</span><strong>'.number_format((int)$summary['approved_requests'],0,',','.').'</strong></div>
        <div class="kpi"><span>Acciones auditadas</span><strong>'.number_format((int)$summary['audit_actions'],0,',','.').'</strong></div>
      </div>
      <p class="muted">La vista pública muestra personajes y estadísticas del sistema, pero nunca expone AccountID.</p>
    </aside></div>';

    if($ui['show_history']){
        $html.='<section class="panel" style="margin-top:14px"><span class="eyebrow">HISTORIAL</span><h3>Actividad reciente</h3><div class="history">';

        if(!$history){
            $html.='<div class="empty">No hay historial disponible.</div>';
        }else{
            foreach($history as $row){
                $action=strtoupper((string)$row['ActionType']);
                $html.='<div class="history-row">
                  <div>'.$e((string)$row['ActionDate']).'</div>
                  <div><span class="badge">'.$e($action).'</span></div>
                  <div><strong>'.$e((string)$row['Character1']).'</strong>'.
                    ((string)$row['Character2']!==''?' · '.$e((string)$row['Character2']):'').
                    ((string)$row['Reason']!==''?'<div class="muted">'.$e((string)$row['Reason']).'</div>':'').
                  '</div>
                </div>';
            }
        }

        $html.='</div></section>';
    }

    $html.='</div>';

    return ['available'=>true,'html'=>$html];
};
