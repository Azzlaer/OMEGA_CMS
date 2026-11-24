<?php
declare(strict_types=1);

require_once __DIR__.'/DevilSquareModuleSettings.php';

return static function(string $hook,array $payload,array $config,array $context): mixed {
    /** @var \Omega\Core\Database $cmsDb */
    $cmsDb=$context['cms_db'];
    /** @var \Omega\Core\Database $muDb */
    $muDb=$context['mu_db'];
    $root=(string)$context['root'];

    $settingsService=new OmegaDevilSquareModuleSettings($cmsDb,$root);

    $e=static fn(mixed $v): string =>
        htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');

    $styles=static function(): string {
        return <<<'CSS'
<style>
.dspro{--red:#d65a31;--gold:#f0b85f;--border:#4b342d;color:#eee}
.dspro *{box-sizing:border-box}
.dspro .ds-hero{position:relative;display:block;min-height:0;height:auto;overflow:hidden;border:1px solid #5b3b2c;border-radius:14px;padding:22px 24px;background:radial-gradient(circle at 82% 20%,rgba(184,68,75,.22),transparent 34%),linear-gradient(135deg,#1a120e,#2b1710 50%,#110d0a);margin-bottom:16px}
.dspro .ds-hero:before{content:"DS";position:absolute;right:24px;top:-34px;font-size:118px;font-weight:900;color:rgba(255,255,255,.04);pointer-events:none}
.dspro .ds-hero .eyebrow{position:relative;font-size:10px;letter-spacing:.16em;color:#c89f87;font-weight:900;text-transform:uppercase}
.dspro .ds-hero h2{position:relative;font-size:25px;line-height:1.2;margin:5px 0 7px;color:#f2d399}
.dspro .ds-hero p{position:relative;max-width:760px;margin:0;line-height:1.55;color:#b9aaa2}
.dspro .ds-banner{margin-bottom:16px;border:1px solid #5b3b2c;border-radius:14px;overflow:hidden;background:#100b08}
.dspro .ds-banner img{display:block;width:100%}
.dspro .ds-summary{display:grid;grid-template-columns:repeat(3,minmax(120px,1fr));gap:9px;margin:0 0 16px}
.dspro .ds-kpi{background:linear-gradient(180deg,#221811,#160f0b);border:1px solid #4b342b;border-radius:10px;padding:12px}
.dspro .ds-kpi span{display:block;font-size:9px;text-transform:uppercase;letter-spacing:.08em;color:#aa8d7a}
.dspro .ds-kpi strong{display:block;margin-top:4px;font-size:22px;color:#f2d399}
.dspro .panel{background:linear-gradient(145deg,#1a1410,#100d0a);border:1px solid #4a352d;border-radius:11px;padding:15px}
.dspro .muted{color:#aa9a8f}
.dspro .admin-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}
.dspro .field{display:grid;gap:6px}
.dspro .field input,.dspro .field select,.dspro .field textarea{width:100%;padding:10px 11px;border:1px solid #573e33;border-radius:8px;background:#120d0a;color:#f3e9e9}
.dspro .field textarea{min-height:88px;resize:vertical}
.dspro .choice{display:grid;grid-template-columns:repeat(3,1fr);gap:8px}
.dspro .choice label{display:flex;gap:8px;align-items:center;border:1px solid #543a31;border-radius:9px;padding:10px;background:#160e10}
.dspro .preview{max-width:100%;border-radius:10px;border:1px solid #573e33;display:block}
.dspro .actions{display:flex;gap:8px;flex-wrap:wrap;margin-top:12px}
.dspro .empty{padding:20px;text-align:center;border:1px dashed #68483a;border-radius:9px;color:#ad988b}
@media(max-width:720px){.dspro .ds-summary{grid-template-columns:1fr}.dspro .admin-grid{grid-template-columns:1fr}.dspro .choice{grid-template-columns:1fr}.dspro .ds-hero:before{font-size:76px}}
</style>
CSS;
    };

    if($hook==='admin.action'){
        $action=(string)($payload['action']??'');
        $data=(array)($payload['data']??[]);
        $files=(array)($payload['files']??[]);

        if($action==='save_settings'){
            $settingsService->save($data);
            return 'Configuración de Devil Square guardada correctamente.';
        }

        if($action==='upload_banner'){
            $settingsService->uploadBanner((array)($files['banner_image']??[]));
            return 'Banner de Devil Square subido y activado correctamente.';
        }

        if($action==='delete_banner'){
            $settingsService->deleteBanner();
            return 'Banner de Devil Square eliminado. Se restauró el diseño Devil Square.';
        }

        throw new RuntimeException('Acción Devil Square no reconocida.');
    }

    if($hook==='ranking.header'){
        $ui=$settingsService->get();
        $stats=(array)($payload['stats']??[]);

        $header='';
        if($ui['header_mode']==='banner' && $ui['banner_path']!==''){
            $header='<div class="ds-banner" style="height:'.(int)$ui['banner_height'].'px"><img src="'.$e($ui['banner_path']).'" alt="'.$e($ui['banner_alt']).'" style="height:100%;object-fit:'.$e($ui['banner_fit']).';object-position:'.$e($ui['banner_position']).'"></div>';
        }elseif($ui['header_mode']==='design'){
            $header='<div class="ds-hero">
              <span class="eyebrow">'.$e($ui['design_eyebrow']).'</span>
              <h2>'.$e($ui['design_title']).'</h2>
              <p>'.$e($ui['design_subtitle']).'</p>
            </div>';
        }

        $summary='';
        if($ui['show_summary']){
            $summary='<div class="ds-summary">
              <div class="ds-kpi"><span>Participantes</span><strong>'.number_format((int)($stats['participants']??0),0,',','.').'</strong></div>
              <div class="ds-kpi"><span>Mejor score</span><strong>'.number_format((int)($stats['bestScore']??0),0,',','.').'</strong></div>
              <div class="ds-kpi"><span>Score promedio</span><strong>'.number_format((float)($stats['averageScore']??0),0,',','.').'</strong></div>
            </div>';
        }

        return $styles().'<div class="dspro">'.$header.$summary.'</div>';
    }

    if($hook==='admin.panel'){
        $ui=$settingsService->get();
        $token=$e((string)($payload['csrf_token']??''));

        $tableExists=false;
        try{
            $row=$muDb->fetchOne(
                "SELECT COUNT(*) total FROM INFORMATION_SCHEMA.TABLES
                 WHERE TABLE_SCHEMA='dbo' AND TABLE_NAME='RankingDevilSquare'"
            );
            $tableExists=(int)($row['total']??0)>0;
        }catch(Throwable){}

        $bannerPreview=$ui['banner_path']!==''
            ? '<img class="preview" src="'.$e($ui['banner_path']).'" alt="'.$e($ui['banner_alt']).'">'
            : '<div class="empty">No hay banner personalizado cargado.</div>';

        return $styles().'<div class="dspro">
          <div class="panel">
            <span class="eyebrow">APARIENCIA</span>
            <h3>Cabecera del Center Devil Square</h3>
            <p class="muted">Elige un solo modo. Diseño y banner nunca se muestran juntos.</p>

            <form method="post" action="/admin/modules/action">
              <input type="hidden" name="_csrf" value="'.$token.'">
              <input type="hidden" name="slug" value="event-devilsquare-pro">
              <input type="hidden" name="module_action" value="save_settings">

              <div class="choice">
                <label><input type="radio" name="header_mode" value="design" '.($ui['header_mode']==='design'?'checked':'').'> Diseño Devil Square</label>
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
              </div>

              <div class="choice" style="margin-top:14px">
                <label><input type="checkbox" name="show_summary" value="1" '.($ui['show_summary']?'checked':'').'> Mostrar resumen estadístico</label>
              </div>

              <div class="actions">
                <button class="btn btn-primary" type="submit">Guardar configuración</button>
                <a class="btn" href="/rankings?type=devilsquare" target="_blank" rel="noopener">Vista previa</a>
              </div>
            </form>
          </div>

          <div class="panel" style="margin-top:14px">
            <span class="eyebrow">BANNER PERSONALIZADO</span>
            <h3>Imagen del Center Devil Square</h3>
            <p class="muted">JPG, PNG o WEBP · máximo 8 MB · recomendado 1600×420.</p>
            '.$bannerPreview.'
            <div class="actions">
              <form method="post" action="/admin/modules/action" enctype="multipart/form-data">
                <input type="hidden" name="_csrf" value="'.$token.'">
                <input type="hidden" name="slug" value="event-devilsquare-pro">
                <input type="hidden" name="module_action" value="upload_banner">
                <input type="file" name="banner_image" accept="image/jpeg,image/png,image/webp" required>
                <button class="btn btn-primary" type="submit">Subir y usar banner</button>
              </form>'.
              ($ui['banner_path']!==''?'<form method="post" action="/admin/modules/action" onsubmit="return confirm(\'¿Eliminar el banner de Devil Square?\')">
                <input type="hidden" name="_csrf" value="'.$token.'">
                <input type="hidden" name="slug" value="event-devilsquare-pro">
                <input type="hidden" name="module_action" value="delete_banner">
                <button class="btn btn-danger" type="submit">Eliminar banner</button>
              </form>':'').'
            </div>
          </div>

          <div class="panel" style="margin-top:14px">
            <span class="eyebrow">DIAGNÓSTICO</span>
            <h3>RankingDevilSquare</h3>
            <p>'.($tableExists?'✅ Tabla detectada correctamente.':'⚠ No se encontró la tabla RankingDevilSquare.').'</p>
            <p class="muted">El módulo no modifica el ranking del GameServer.</p>
          </div>
        </div>';
    }

    return null;
};
