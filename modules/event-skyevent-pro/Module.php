<?php
declare(strict_types=1);

require_once __DIR__.'/SkyEventModuleSettings.php';

return static function(string $hook,array $payload,array $config,array $context): mixed {
    /** @var \Omega\Core\Database $cmsDb */
    $cmsDb=$context['cms_db'];
    /** @var \Omega\Core\Database $muDb */
    $muDb=$context['mu_db'];
    $root=(string)$context['root'];

    $settingsService=new OmegaSkyEventModuleSettings($cmsDb,$root);

    $e=static fn(mixed $v): string =>
        htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');

    $styles=static function(): string {
        return <<<'CSS'
<style>
.skypro{--sky:#71c8ff;--sky2:#d9f2ff;--panel:#101923;--border:#2a3d4d;--muted:#8fa3b2;color:#e8eef3}
.skypro *{box-sizing:border-box}
.skypro .sky-hero{position:relative;display:block;min-height:0;height:auto;overflow:hidden;border:1px solid #31516a;border-radius:14px;padding:22px 24px;background:radial-gradient(circle at 78% 20%,rgba(113,200,255,.18),transparent 34%),linear-gradient(135deg,#0c1823,#102738 52%,#0d1720);margin-bottom:16px}
.skypro .sky-hero:before{content:"SKY";position:absolute;right:24px;top:-34px;font-size:118px;font-weight:900;letter-spacing:-.08em;color:rgba(255,255,255,.04);pointer-events:none}
.skypro .sky-hero .eyebrow{position:relative;font-size:10px;letter-spacing:.16em;color:#8bb9d6;font-weight:900;text-transform:uppercase}
.skypro .sky-hero h2{position:relative;font-size:25px;line-height:1.2;margin:5px 0 7px;color:#d8f2ff}
.skypro .sky-hero p{position:relative;max-width:760px;margin:0;line-height:1.55;color:#9db1bf}
.skypro .sky-banner{margin-bottom:16px;border:1px solid #31516a;border-radius:14px;overflow:hidden;background:#091018}
.skypro .sky-banner img{display:block;width:100%}
.skypro .sky-summary{display:grid;grid-template-columns:repeat(4,minmax(120px,1fr));gap:9px;margin:0 0 16px}
.skypro .sky-kpi{background:linear-gradient(180deg,#132432,#0d1821);border:1px solid var(--border);border-radius:10px;padding:12px}
.skypro .sky-kpi span{display:block;font-size:9px;text-transform:uppercase;letter-spacing:.08em;color:#7694a8}
.skypro .sky-kpi strong{display:block;margin-top:4px;font-size:22px;color:#d8f2ff}
.skypro .panel{background:linear-gradient(145deg,#111a23,#0c141c);border:1px solid var(--border);border-radius:11px;padding:15px}
.skypro .muted{color:var(--muted)}
.skypro .admin-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}
.skypro .field{display:grid;gap:6px}
.skypro .field input,.skypro .field textarea{width:100%;padding:10px 11px;border:1px solid #344c5d;border-radius:8px;background:#0b141c;color:#e9f0f5}
.skypro .field textarea{min-height:88px;resize:vertical}
.skypro .choice{display:grid;grid-template-columns:repeat(3,1fr);gap:8px}
.skypro .choice label{display:flex;gap:8px;align-items:center;border:1px solid #2c4354;border-radius:9px;padding:10px;background:#0c151d}
.skypro .preview{max-width:100%;border-radius:10px;border:1px solid #334c5d;display:block}
.skypro .actions{display:flex;gap:8px;flex-wrap:wrap;margin-top:12px}
.skypro .empty{padding:20px;text-align:center;border:1px dashed #355268;border-radius:9px;color:#8ca8b9}
@media(max-width:720px){.skypro .sky-summary{grid-template-columns:1fr 1fr}.skypro .admin-grid{grid-template-columns:1fr}.skypro .choice{grid-template-columns:1fr}.skypro .sky-hero:before{font-size:76px}}
</style>
CSS;
    };

    if($hook==='admin.action'){
        $action=(string)($payload['action']??'');
        $data=(array)($payload['data']??[]);
        $files=(array)($payload['files']??[]);

        if($action==='save_settings'){
            $settingsService->save($data);
            return 'Configuración de SkyEvent guardada correctamente.';
        }

        if($action==='upload_banner'){
            $settingsService->uploadBanner((array)($files['banner_image']??[]));
            return 'Banner de SkyEvent subido y activado correctamente.';
        }

        if($action==='delete_banner'){
            $settingsService->deleteBanner();
            return 'Banner de SkyEvent eliminado. Se restauró el diseño SkyEvent.';
        }

        throw new RuntimeException('Acción SkyEvent no reconocida.');
    }

    if($hook==='ranking.settings'){
        return $settingsService->get();
    }

    if($hook==='ranking.header'){
        $ui=$settingsService->get();
        $rows=(array)($payload['rows']??[]);

        $header='';
        if($ui['header_mode']==='banner' && $ui['banner_path']!==''){
            $header='<div class="sky-banner" style="height:'.(int)$ui['banner_height'].'px"><img src="'.$e($ui['banner_path']).'" alt="'.$e($ui['banner_alt']).'" style="height:100%;object-fit:'.$e($ui['banner_fit']).';object-position:'.$e($ui['banner_position']).'"></div>';
        }elseif($ui['header_mode']==='design'){
            $header='<div class="sky-hero">
              <span class="eyebrow">'.$e($ui['design_eyebrow']).'</span>
              <h2>'.$e($ui['design_title']).'</h2>
              <p>'.$e($ui['design_subtitle']).'</p>
            </div>';
        }

        $summary='';
        if($ui['show_summary']){
            $players=count($rows);
            $events=0;$wins=0;$score=0;$best=0;
            foreach($rows as $row){
                $events+=(int)($row['EventsPlayed']??0);
                $wins+=(int)($row['Wins']??0);
                $score+=(int)($row['TotalScore']??0);
                $best=max($best,(int)($row['BestScore']??0));
            }

            $summary='<div class="sky-summary">
              <div class="sky-kpi"><span>Jugadores</span><strong>'.number_format($players,0,',','.').'</strong></div>
              <div class="sky-kpi"><span>Eventos jugados</span><strong>'.number_format($events,0,',','.').'</strong></div>
              <div class="sky-kpi"><span>Victorias</span><strong>'.number_format($wins,0,',','.').'</strong></div>
              <div class="sky-kpi"><span>Mejor score</span><strong>'.number_format($best,0,',','.').'</strong></div>
            </div>';
        }

        return $styles().'<div class="skypro">'.$header.$summary.'</div>';
    }

    if($hook==='admin.panel'){
        $ui=$settingsService->get();
        $token=$e((string)($payload['csrf_token']??''));

        $tableExists=false;
        try{
            $row=$muDb->fetchOne(
                "SELECT COUNT(*) total FROM INFORMATION_SCHEMA.TABLES
                 WHERE TABLE_SCHEMA='dbo' AND TABLE_NAME='SkyEventRankingTotal'"
            );
            $tableExists=(int)($row['total']??0)>0;
        }catch(Throwable){}

        $bannerPreview=$ui['banner_path']!==''
            ? '<img class="preview" src="'.$e($ui['banner_path']).'" alt="'.$e($ui['banner_alt']).'">'
            : '<div class="empty">No hay banner personalizado cargado.</div>';

        return $styles().'<div class="skypro">
          <div class="panel">
            <span class="eyebrow">APARIENCIA</span>
            <h3>Cabecera del Center SkyEvent</h3>
            <p class="muted">Elige un solo modo. Diseño y banner nunca se muestran juntos.</p>

            <form method="post" action="/admin/modules/action">
              <input type="hidden" name="_csrf" value="'.$token.'">
              <input type="hidden" name="slug" value="event-skyevent-pro">
              <input type="hidden" name="module_action" value="save_settings">

              <div class="choice">
                <label><input type="radio" name="header_mode" value="design" '.($ui['header_mode']==='design'?'checked':'').'> Diseño SkyEvent</label>
                <label><input type="radio" name="header_mode" value="banner" '.($ui['header_mode']==='banner'?'checked':'').'> Banner personalizado</label>
                <label><input type="radio" name="header_mode" value="none" '.($ui['header_mode']==='none'?'checked':'').'> Sin cabecera</label>
              </div>

              <div class="admin-grid" style="margin-top:14px">
                <label class="field">Eyebrow
                  <input name="design_eyebrow" maxlength="120" value="'.$e($ui['design_eyebrow']).'">
                </label>
                <label class="field">Título
                  <input name="design_title" maxlength="160" value="'.$e($ui['design_title']).'">
                </label>
                <label class="field" style="grid-column:1/-1">Descripción
                  <textarea name="design_subtitle" maxlength="500">'.$e($ui['design_subtitle']).'</textarea>
                </label>
                <label class="field">Texto alternativo del banner
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
                <label class="field">Cantidad de jugadores
                  <input type="number" name="ranking_limit" min="10" max="100" value="'.(int)$ui['ranking_limit'].'">
                </label>
              </div>

              <div class="choice" style="margin-top:14px">
                <label><input type="checkbox" name="show_summary" value="1" '.($ui['show_summary']?'checked':'').'> Mostrar resumen estadístico</label>
              </div>

              <div class="actions">
                <button class="btn btn-primary" type="submit">Guardar configuración SkyEvent</button>
                <a class="btn" href="/rankings?type=skyevent" target="_blank" rel="noopener">Vista previa</a>
              </div>
            </form>
          </div>

          <div class="panel" style="margin-top:14px">
            <span class="eyebrow">BANNER PERSONALIZADO</span>
            <h3>Imagen del Center SkyEvent</h3>
            <p class="muted">JPG, PNG o WEBP · máximo 8 MB · recomendado 1600×420.</p>
            '.$bannerPreview.'
            <div class="actions">
              <form method="post" action="/admin/modules/action" enctype="multipart/form-data">
                <input type="hidden" name="_csrf" value="'.$token.'">
                <input type="hidden" name="slug" value="event-skyevent-pro">
                <input type="hidden" name="module_action" value="upload_banner">
                <input type="file" name="banner_image" accept="image/jpeg,image/png,image/webp" required>
                <button class="btn btn-primary" type="submit">Subir y usar banner</button>
              </form>'.
              ($ui['banner_path']!==''?'<form method="post" action="/admin/modules/action" onsubmit="return confirm(\'¿Eliminar el banner de SkyEvent?\')">
                <input type="hidden" name="_csrf" value="'.$token.'">
                <input type="hidden" name="slug" value="event-skyevent-pro">
                <input type="hidden" name="module_action" value="delete_banner">
                <button class="btn btn-danger" type="submit">Eliminar banner</button>
              </form>':'').'
            </div>
          </div>

          <div class="panel" style="margin-top:14px">
            <span class="eyebrow">DIAGNÓSTICO</span>
            <h3>SkyEventRankingTotal</h3>
            <p class="'.($tableExists?'':'muted').'">'.($tableExists?'✅ Tabla detectada correctamente.':'⚠ No se encontró la tabla SkyEventRankingTotal.').'</p>
            <p class="muted">El módulo solo configura la presentación. Los datos continúan siendo leídos por el driver MuEmu de OMEGA.</p>
          </div>
        </div>';
    }

    return null;
};
