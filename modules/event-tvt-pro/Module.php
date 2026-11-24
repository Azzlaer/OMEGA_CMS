<?php
declare(strict_types=1);

require_once __DIR__.'/TvTEventService.php';
require_once __DIR__.'/TvTModuleSettings.php';

return static function(string $hook,array $payload,array $config,array $context): mixed {
    /** @var \Omega\Core\Database $muDb */
    $muDb=$context['mu_db'];
    /** @var \Omega\Core\Database $cmsDb */
    $cmsDb=$context['cms_db'];
    $root=(string)$context['root'];

    $service=new OmegaTvTEventService($muDb);
    $settingsService=new OmegaTvTModuleSettings($cmsDb,$root);

    $e=static fn(mixed $v): string =>
        htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');

    $cfgBool=static function(string $key,bool $default=false) use($config): bool {
        if(!array_key_exists($key,$config)) return $default;
        $v=$config[$key];
        if(is_bool($v)) return $v;
        return in_array(strtolower((string)$v),['1','true','yes','on'],true);
    };

    $styles=static function(): string {
        return <<<'CSS'
<style>
.tvt{--blue:#4ba7ff;--red:#ff5f67;--gold:#e0ba62;--bg:#0b1218;--panel:#101923;--border:#283847;--muted:#8fa3b2;color:#e8eef3}
.tvt *{box-sizing:border-box}.tvt a{text-decoration:none}
.tvt .tvt-hero{position:relative;display:block;min-height:0;height:auto;overflow:hidden;border:1px solid #384757;border-radius:14px;padding:22px 24px;background:radial-gradient(circle at 12% 20%,rgba(75,167,255,.18),transparent 32%),radial-gradient(circle at 88% 20%,rgba(255,95,103,.16),transparent 32%),linear-gradient(135deg,#0c1722,#11151c 50%,#211012);margin-bottom:16px}
.tvt .tvt-hero:before{content:"TVT";position:absolute;right:22px;top:-34px;font-size:118px;font-weight:900;letter-spacing:-.08em;color:rgba(255,255,255,.035);pointer-events:none}
.tvt .tvt-hero h2{position:relative;font-size:25px;line-height:1.2;margin:5px 0 7px}
.tvt .tvt-hero p{position:relative;max-width:760px;margin:0;line-height:1.55}
.tvt .tvt-banner{margin-bottom:16px;border:1px solid #384757;border-radius:14px;overflow:hidden;background:#090f14}
.tvt .tvt-banner img{display:block;width:100%;height:auto;max-height:420px;object-fit:cover}
.tvt .tvt-admin-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}
.tvt .tvt-admin-field{display:grid;gap:6px}
.tvt .tvt-admin-field input,.tvt .tvt-admin-field select,.tvt .tvt-admin-field textarea{width:100%;padding:10px 11px;border:1px solid #344553;border-radius:8px;background:#0b141c;color:#e9f0f5}
.tvt .tvt-admin-field textarea{min-height:88px;resize:vertical}
.tvt .tvt-choice{display:grid;grid-template-columns:repeat(3,1fr);gap:8px}
.tvt .tvt-choice label{display:flex;gap:8px;align-items:center;border:1px solid #2c3e4d;border-radius:9px;padding:10px;background:#0c151d}
.tvt .tvt-banner-preview{max-width:100%;border-radius:10px;border:1px solid #334452;display:block}
.tvt .tvt-admin-actions{display:flex;gap:8px;flex-wrap:wrap;margin-top:12px}
.tvt .eyebrow{font-size:10px;letter-spacing:.16em;color:#91a4b3;font-weight:900;text-transform:uppercase}
.tvt h2,.tvt h3{margin:4px 0;color:#f2d37f}.tvt .muted{color:var(--muted)}
.tvt .teams{display:flex;gap:8px;align-items:center;flex-wrap:wrap}.tvt .blue{color:#75bdff}.tvt .red{color:#ff7c82}.tvt .win{color:#76dda5}.tvt .loss{color:#ff8989}
.tvt .kpis{display:grid;grid-template-columns:repeat(6,minmax(120px,1fr));gap:9px;margin:14px 0}
.tvt .kpi{background:linear-gradient(180deg,#15202b,#0e161e);border:1px solid var(--border);border-radius:10px;padding:12px}
.tvt .kpi span{display:block;font-size:9px;text-transform:uppercase;letter-spacing:.08em;color:#8297a7}.tvt .kpi strong{display:block;margin-top:4px;font-size:22px;color:#f2ddb1}
.tvt .grid{display:grid;grid-template-columns:minmax(0,1.35fr) minmax(300px,.65fr);gap:14px}
.tvt .panel{background:linear-gradient(145deg,#111a23,#0c141c);border:1px solid var(--border);border-radius:11px;padding:15px}
.tvt .toolbar{display:flex;gap:8px;flex-wrap:wrap;margin:0 0 13px}.tvt .btn{display:inline-flex;align-items:center;justify-content:center;padding:8px 12px;border:1px solid #435363;border-radius:7px;background:#121c25;color:#dce6ed;font-weight:800}.tvt .btn:hover{border-color:#94783c;color:#f1d27e}
.tvt .table-wrap{overflow:auto;border:1px solid #243441;border-radius:9px}.tvt table{width:100%;border-collapse:collapse;min-width:780px}.tvt th{padding:9px 10px;text-align:left;background:#121c25;color:#8297a7;font-size:9px;text-transform:uppercase;letter-spacing:.07em}.tvt td{padding:10px;border-bottom:1px solid #1e2c37;font-size:12px}.tvt tbody tr:hover{background:rgba(255,255,255,.025)}
.tvt .badge{display:inline-flex;padding:3px 7px;border-radius:999px;border:1px solid #3b4b59;font-size:9px;font-weight:900}.tvt .badge.win{background:rgba(79,184,119,.1);border-color:rgba(91,210,139,.28)}.tvt .badge.loss{background:rgba(212,76,82,.1);border-color:rgba(255,95,103,.25)}
.tvt .rank{display:grid;gap:7px}.tvt .rank-row{display:grid;grid-template-columns:38px minmax(110px,1.4fr) repeat(5,minmax(55px,.6fr));gap:7px;align-items:center;padding:9px;border:1px solid #22323f;border-radius:8px;background:#0b131b}.tvt .rank-pos{font-size:18px;font-weight:900;color:#e1bb65}.tvt .rank-name strong{display:block}.tvt .rank-name small{color:#728797}.tvt .stat small{display:block;color:#6f8495;font-size:8px;text-transform:uppercase}.tvt .stat b{font-size:13px}
.tvt .session{border-left:3px solid #86703e}.tvt .session-head{display:flex;justify-content:space-between;gap:10px;align-items:flex-start;flex-wrap:wrap}.tvt code{color:#a8bac6}
.tvt .participants{display:grid;grid-template-columns:1fr 1fr;gap:10px}.tvt .team-card{border:1px solid #2b3945;border-radius:9px;padding:12px;background:#0b1219}.tvt .team-card.blue-team{border-color:rgba(75,167,255,.35)}.tvt .team-card.red-team{border-color:rgba(255,95,103,.35)}
.tvt .timeline{display:grid;gap:6px}.tvt .event{display:grid;grid-template-columns:135px 150px minmax(150px,1fr) 80px;gap:8px;padding:8px;border-bottom:1px solid #1f2d38;align-items:center}.tvt .event:last-child{border-bottom:0}.tvt .event-time{color:#718697;font-variant-numeric:tabular-nums}.tvt .event-type{font-weight:900;color:#d7b760}.tvt .event-detail{color:#9eb0bc}
.tvt .empty{padding:25px;text-align:center;border:1px dashed #354858;border-radius:9px;color:#8ca0af}.tvt .mine{box-shadow:inset 3px 0 0 #d4ad54;background:rgba(212,173,84,.04)}
@media(max-width:1100px){.tvt .kpis{grid-template-columns:repeat(3,1fr)}.tvt .grid{grid-template-columns:1fr}}
@media(max-width:720px){.tvt .kpis{grid-template-columns:1fr 1fr}.tvt .participants{grid-template-columns:1fr}.tvt .rank-row{grid-template-columns:32px 1fr 1fr}.tvt .rank-row .stat:nth-child(n+4){display:none}.tvt .event{grid-template-columns:1fr}.tvt .tvt-hero:before{font-size:76px}.tvt .tvt-admin-grid{grid-template-columns:1fr}.tvt .tvt-choice{grid-template-columns:1fr}}
</style>
CSS;
    };

    $actionLabel=static function(string $action,string $detail,int $value): string {
        return match($action){
            'ENTRY'=>'Entró al equipo '.($detail!==''?$detail:'?'),
            'OBJECTIVE_DESTROYED'=>'Objetivo destruido'.($value>0?' #'.$value:''),
            'SCORE'=>'Resultado '.$detail.($value>0?' · score '.$value:''),
            'KILLS'=>'Kills finales: '.$value,
            'DEATHS'=>'Muertes finales: '.$value,
            'KILL'=>'Kill sobre '.($detail!==''?$detail:'rival'),
            'DEATH'=>'Murió contra '.($detail!==''?$detail:'rival'),
            'EXIT'=>'Salida · '.str_replace('_',' ',$detail),
            'REWARD_ZEN'=>'Recompensa Zen: '.number_format($value,0,',','.'),
            default=>$action.($detail!==''?' · '.$detail:'').($value!==0?' · '.$value:''),
        };
    };

    if($hook==='admin.action'){
        $action=(string)($payload['action']??'');
        $data=(array)($payload['data']??[]);
        $files=(array)($payload['files']??[]);

        if($action==='save_settings'){
            $settingsService->save($data);
            return 'Configuración TvT guardada correctamente.';
        }

        if($action==='upload_banner'){
            $settingsService->uploadBanner((array)($files['banner_image']??[]));
            return 'Banner TvT subido y activado correctamente.';
        }

        if($action==='delete_banner'){
            $settingsService->deleteBanner();
            return 'Banner TvT eliminado. Se restauró el diseño TvT.';
        }

        throw new RuntimeException('Acción TvT no reconocida.');
    }

    if($hook==='admin.panel'){
        $ui=$settingsService->get();
        $diag=$service->diagnostics();
        $ready=true;$cards='';$missingRows='';

        foreach($diag as $table=>$d){
            if(!$d['exists'] || $d['missing']) $ready=false;
            $cards.='<div class="kpi"><span>'.$e($table).'</span><strong class="'.($d['exists']&&!$d['missing']?'win':'loss').'">'.($d['exists']&&!$d['missing']?'ONLINE':'REVISAR').'</strong><small class="muted">'.number_format((int)$d['count'],0,',','.').' registros</small></div>';
            if($d['missing']){
                $missingRows.='<tr><td>'.$e($table).'</td><td class="loss">'.$e(implode(', ',$d['missing'])).'</td></tr>';
            }
        }

        $overview=$ready?$service->overview():['matches'=>0,'results'=>0,'players'=>0,'wins'=>0,'kills'=>0,'objectives'=>0,'zen'=>0];
        $token=$e((string)($payload['csrf_token']??''));

        $bannerPreview=$ui['banner_path']!==''
            ? '<img class="tvt-banner-preview" src="'.$e($ui['banner_path']).'" alt="'.$e($ui['banner_alt']).'">'
            : '<div class="empty">No hay banner personalizado cargado.</div>';

        return $styles().'<div class="tvt">
          <div class="panel">
            <span class="eyebrow">APARIENCIA</span>
            <h3>Cabecera del Center TvT</h3>
            <p class="muted">Elige un solo modo. Diseño TvT y Banner personalizado nunca se muestran juntos.</p>

            <form method="post" action="/admin/modules/action">
              <input type="hidden" name="_csrf" value="'.$token.'">
              <input type="hidden" name="slug" value="event-tvt-pro">
              <input type="hidden" name="module_action" value="save_settings">

              <div class="tvt-choice">
                <label><input type="radio" name="header_mode" value="design" '.($ui['header_mode']==='design'?'checked':'').'> Diseño TvT</label>
                <label><input type="radio" name="header_mode" value="banner" '.($ui['header_mode']==='banner'?'checked':'').'> Banner personalizado</label>
                <label><input type="radio" name="header_mode" value="none" '.($ui['header_mode']==='none'?'checked':'').'> Sin cabecera</label>
              </div>

              <div class="tvt-admin-grid" style="margin-top:14px">
                <label class="tvt-admin-field">Eyebrow del diseño
                  <input name="design_eyebrow" maxlength="120" value="'.$e($ui['design_eyebrow']).'">
                </label>
                <label class="tvt-admin-field">Título del diseño
                  <input name="design_title" maxlength="160" value="'.$e($ui['design_title']).'">
                </label>
                <label class="tvt-admin-field" style="grid-column:1/-1">Descripción del diseño
                  <textarea name="design_subtitle" maxlength="500">'.$e($ui['design_subtitle']).'</textarea>
                </label>
                <label class="tvt-admin-field">Texto alternativo del banner
                  <input name="banner_alt" maxlength="180" value="'.$e($ui['banner_alt']).'">
                </label>
                <label class="tvt-admin-field">Partidas recientes
                  <input type="number" name="recent_matches" min="5" max="100" value="'.(int)$ui['recent_matches'].'">
                </label>
                <label class="tvt-admin-field">Jugadores en ranking
                  <input type="number" name="ranking_limit" min="10" max="100" value="'.(int)$ui['ranking_limit'].'">
                </label>
                <label class="tvt-admin-field">Acciones por partida
                  <input type="number" name="timeline_limit" min="20" max="300" value="'.(int)$ui['timeline_limit'].'">
                </label>
              </div>

              <div class="tvt-choice" style="margin-top:14px">
                <label><input type="checkbox" name="show_account_characters" value="1" '.($ui['show_account_characters']?'checked':'').'> Mostrar “Mis personajes”</label>
                <label><input type="checkbox" name="show_rewards" value="1" '.($ui['show_rewards']?'checked':'').'> Mostrar recompensas Zen</label>
                <label><input type="checkbox" name="show_objectives" value="1" '.($ui['show_objectives']?'checked':'').'> Mostrar objetivos</label>
              </div>

              <div class="tvt-admin-actions">
                <button class="btn btn-primary" type="submit">Guardar configuración TvT</button>
                <a class="btn" href="/rankings?type=tvt" target="_blank" rel="noopener">Vista previa</a>
              </div>
            </form>
          </div>

          <div class="panel" style="margin-top:14px">
            <span class="eyebrow">BANNER PERSONALIZADO</span>
            <h3>Imagen del Center TvT</h3>
            <p class="muted">JPG, PNG o WEBP · máximo 8 MB · recomendado 1600×420 o proporción similar.</p>
            '.$bannerPreview.'
            <div class="tvt-admin-actions">
              <form method="post" action="/admin/modules/action" enctype="multipart/form-data">
                <input type="hidden" name="_csrf" value="'.$token.'">
                <input type="hidden" name="slug" value="event-tvt-pro">
                <input type="hidden" name="module_action" value="upload_banner">
                <input type="file" name="banner_image" accept="image/jpeg,image/png,image/webp" required>
                <button class="btn btn-primary" type="submit">Subir y usar banner</button>
              </form>'.
              ($ui['banner_path']!==''?'<form method="post" action="/admin/modules/action" onsubmit="return confirm(\'¿Eliminar el banner TvT?\')">
                <input type="hidden" name="_csrf" value="'.$token.'">
                <input type="hidden" name="slug" value="event-tvt-pro">
                <input type="hidden" name="module_action" value="delete_banner">
                <button class="btn btn-danger" type="submit">Eliminar banner</button>
              </form>':'').'
            </div>
          </div>

          <div class="panel" style="margin-top:14px">
            <span class="eyebrow">DIAGNÓSTICO</span>
            <h3>Datos TvT</h3>
            <div class="kpis">'.$cards.'</div>
            <div class="kpis">
              <div class="kpi"><span>Partidas</span><strong>'.number_format((int)$overview['matches'],0,',','.').'</strong></div>
              <div class="kpi"><span>Resultados</span><strong>'.number_format((int)$overview['results'],0,',','.').'</strong></div>
              <div class="kpi"><span>Jugadores</span><strong>'.number_format((int)$overview['players'],0,',','.').'</strong></div>
              <div class="kpi"><span>Kills</span><strong>'.number_format((int)$overview['kills'],0,',','.').'</strong></div>
              <div class="kpi"><span>Objetivos</span><strong>'.number_format((int)$overview['objectives'],0,',','.').'</strong></div>
              <div class="kpi"><span>Zen entregado</span><strong>'.number_format((int)$overview['zen'],0,',','.').'</strong></div>
            </div>'.
            ($missingRows!==''?'<div class="table-wrap"><table><thead><tr><th>Tabla</th><th>Columnas faltantes</th></tr></thead><tbody>'.$missingRows.'</tbody></table></div>':'').
          '</div>
        </div>';
    }

    if($hook!=='ranking.page') return null;

    $accountId=trim((string)($payload['account_id']??''));

    if(!$service->ready()){
        return [
            'available'=>false,
            'html'=>$styles().'<div class="tvt"><div class="empty"><h3>TvT no está disponible</h3><p>Falta alguna de las tablas o columnas requeridas: <code>TvTMatch</code>, <code>TvTResult</code> y <code>TvTAction</code>.</p></div></div>'
        ];
    }

    $query=(array)($payload['query']??[]);
    $sessionKey=trim((string)($query['session']??''));
    $playerName=trim((string)($query['player']??''));

    $ui=$settingsService->get();
    $showAccountCharacters=(bool)$ui['show_account_characters'];
    $accountCharacters=($showAccountCharacters && $accountId!=='')
        ? $service->accountCharacters($accountId)
        : [];
    $ownedNames=array_values(array_filter(array_map(
        static fn(array $row): string => trim((string)($row['Name']??'')),
        $accountCharacters
    )));

    $myCharacter=trim((string)($query['my_character']??''));
    if($myCharacter!=='' && in_array($myCharacter,$ownedNames,true)){
        $playerName=$myCharacter;
    }

    $recentLimit=max(5,min(100,(int)$ui['recent_matches']));
    $rankingLimit=max(10,min(100,(int)$ui['ranking_limit']));
    $timelineLimit=max(20,min(300,(int)$ui['timeline_limit']));
    $showRewards=(bool)$ui['show_rewards'];
    $showObjectives=(bool)$ui['show_objectives'];

    $overview=$service->overview();
    $ranking=$service->ranking($rankingLimit);
    $matches=$service->recentMatches($recentLimit);

    $headerHtml='';
    if($ui['header_mode']==='banner' && $ui['banner_path']!==''){
        $headerHtml='<div class="tvt-banner"><img src="'.$e($ui['banner_path']).'" alt="'.$e($ui['banner_alt']).'"></div>';
    }elseif($ui['header_mode']==='design'){
        $headerHtml='<div class="tvt-hero">
          <span class="eyebrow">'.$e($ui['design_eyebrow']).'</span>
          <h2>'.$e($ui['design_title']).'</h2>
          <p class="muted">'.$e($ui['design_subtitle']).'</p>
        </div>';
    }

    $html=$styles().'<div class="tvt">'.$headerHtml.'
      <div class="kpis">
        <div class="kpi"><span>Partidas</span><strong>'.number_format((int)$overview['matches'],0,',','.').'</strong></div>
        <div class="kpi"><span>Participaciones</span><strong>'.number_format((int)$overview['results'],0,',','.').'</strong></div>
        <div class="kpi"><span>Jugadores</span><strong>'.number_format((int)$overview['players'],0,',','.').'</strong></div>
        <div class="kpi"><span>Kills</span><strong>'.number_format((int)$overview['kills'],0,',','.').'</strong></div>'.
        ($showObjectives?'<div class="kpi"><span>Objetivos</span><strong>'.number_format((int)$overview['objectives'],0,',','.').'</strong></div>':'').
        ($showRewards?'<div class="kpi"><span>Zen entregado</span><strong>'.number_format((int)$overview['zen'],0,',','.').'</strong></div>':'').
      '</div>';

    if($showAccountCharacters && $accountId!==''){
        $html.='<div class="panel" style="margin-bottom:14px">
          <span class="eyebrow">MI CUENTA · TVT</span>
          <h3>Mis personajes</h3>';

        if($accountCharacters){
            $html.='<form method="get" action="/rankings" class="toolbar">
              <input type="hidden" name="type" value="tvt">
              <label>Personaje
                <select name="my_character">
                  <option value="">Selecciona un personaje</option>';

            foreach($accountCharacters as $character){
                $name=(string)($character['Name']??'');
                $html.='<option value="'.$e($name).'" '.($playerName===$name?'selected':'').'>'.$e($name).
                    ' · Lv '.number_format((int)($character['cLevel']??0),0,',','.').
                    ' · R'.number_format((int)($character['ResetCount']??0),0,',','.').'</option>';
            }

            $html.='</select></label>
              <button class="btn" type="submit">Ver estadísticas TvT</button>
              '.($myCharacter!==''?'<a class="btn" href="/rankings?type=tvt">Limpiar</a>':'').'
            </form>';
        }else{
            $html.='<p class="muted">Esta cuenta todavía no tiene personajes disponibles.</p>';
        }

        $html.='</div>';
    }

    if($sessionKey!==''){
        $session=$service->session($sessionKey,$timelineLimit);
        if($session){
            $match=$session['match'];
            $winnerCharacter='';
            foreach($session['results'] as $r){
                if(strtoupper((string)$r['Result'])==='WIN'){
                    $winnerCharacter=(string)$r['CharacterName'];
                    break;
                }
            }

            $html.='<section class="panel session">
              <div class="session-head">
                <div><span class="eyebrow">MATCH DETAIL</span><h3>'.$e((string)$match['EventName']).'</h3><code>'.$e((string)$match['SessionKey']).'</code></div>
                <div><a class="btn" href="/rankings?type=tvt">← Volver</a></div>
              </div>
              <div class="kpis">
                <div class="kpi"><span>Inicio</span><strong style="font-size:14px">'.$e((string)$match['StartedAt']).'</strong></div>
                <div class="kpi"><span>Estado DB</span><strong>'.$e((string)$match['Status']).'</strong></div>
                <div class="kpi"><span>Ganador</span><strong class="win">'.$e($winnerCharacter!==''?$winnerCharacter:((string)($match['WinnerTeam']??'—'))).'</strong></div>
                <div class="kpi"><span>Blue Score</span><strong class="blue">'.(int)$match['BlueScore'].'</strong></div>
                <div class="kpi"><span>Red Score</span><strong class="red">'.(int)$match['RedScore'].'</strong></div>
                <div class="kpi"><span>Acciones</span><strong>'.count($session['timeline']).'</strong></div>
              </div>';

            $blue=[];$red=[];$unknown=[];
            foreach($session['results'] as $r){
                $team=strtoupper((string)($r['Team']??''));
                if($team==='BLUE') $blue[]=$r;
                elseif($team==='RED') $red[]=$r;
                else $unknown[]=$r;
            }

            $renderTeam=static function(array $rows,string $teamClass,string $title) use($e,$showRewards,$showObjectives,$accountId): string {
                $out='<div class="team-card '.$teamClass.'"><h3>'.$e($title).'</h3>';
                if(!$rows) return $out.'<p class="muted">Sin participantes detectados.</p></div>';
                foreach($rows as $r){
                    $mine=strcasecmp((string)$r['Account'],$accountId)===0?' mine':'';
                    $out.='<div class="rank-row'.$mine.'" style="grid-template-columns:minmax(130px,1.3fr) repeat(4,minmax(55px,.6fr))">
                      <div class="rank-name"><strong>'.$e((string)$r['CharacterName']).'</strong><small>'.$e((string)$r['Account']).'</small></div>
                      <div class="stat"><small>Resultado</small><b class="'.(strtoupper((string)$r['Result'])==='WIN'?'win':'loss').'">'.$e((string)$r['Result']).'</b></div>
                      <div class="stat"><small>Kills</small><b>'.(int)$r['Kills'].'</b></div>
                      <div class="stat"><small>Deaths</small><b>'.(int)$r['Deaths'].'</b></div>
                      <div class="stat"><small>'.($showObjectives?'Objetivos':'Zen').'</small><b>'.($showObjectives?(int)$r['Objectives']:number_format((int)$r['RewardZen'],0,',','.')).'</b></div>
                    </div>';
                    if($showRewards && (int)$r['RewardZen']>0){
                        $out.='<div class="muted" style="padding:4px 8px">💰 Recompensa: '.number_format((int)$r['RewardZen'],0,',','.').' Zen</div>';
                    }
                }
                return $out.'</div>';
            };

            $html.='<div class="participants">'.$renderTeam($blue,'blue-team','🔵 Equipo BLUE').$renderTeam($red,'red-team','🔴 Equipo RED').'</div>';

            if($unknown){
                $html.='<div class="panel" style="margin-top:10px"><h3>Participantes sin equipo detectado</h3>';
                foreach($unknown as $r){
                    $html.='<span class="badge">'.$e((string)$r['CharacterName']).' · '.$e((string)$r['Result']).'</span> ';
                }
                $html.='</div>';
            }

            $html.='<div style="margin-top:16px"><span class="eyebrow">TIMELINE</span><h3>Cronología del combate</h3><div class="timeline">';
            foreach($session['timeline'] as $a){
                $action=(string)$a['Action'];$detail=(string)$a['Detail'];$value=(int)$a['Value'];
                $actor=(string)$a['CharacterName'];
                if($actor==='') $actor=(string)$a['Account'];
                if($actor==='') $actor='Sistema';
                $html.='<div class="event">
                  <div class="event-time">'.$e((string)$a['OccurredAt']).'</div>
                  <div class="event-type">'.$e($action).'</div>
                  <div><strong>'.$e($actor).'</strong><div class="event-detail">'.$e($actionLabel($action,$detail,$value)).'</div></div>
                  <div>'.($value!==0?number_format($value,0,',','.'):'—').'</div>
                </div>';
            }
            $html.='</div></div></section>';
        }else{
            $html.='<div class="empty">No se encontró la sesión TvT solicitada.</div>';
        }
    }elseif($playerName!==''){
        $player=$service->player($playerName);
        if($player){
            $matchesCount=(int)$player['Matches'];$wins=(int)$player['Wins'];$losses=(int)$player['Losses'];
            $winRate=$matchesCount>0?$wins/$matchesCount*100:0;
            $deaths=(int)$player['Deaths'];$kd=$deaths>0?(int)$player['Kills']/$deaths:(float)(int)$player['Kills'];
            $html.='<section class="panel">
              <div class="session-head"><div><span class="eyebrow">PLAYER PROFILE</span><h3>'.$e((string)$player['CharacterName']).'</h3><span class="muted">'.$e((string)$player['Account']).'</span></div><a class="btn" href="/rankings?type=tvt">← Volver</a></div>
              <div class="kpis">
                <div class="kpi"><span>Partidas</span><strong>'.$matchesCount.'</strong></div>
                <div class="kpi"><span>Victorias</span><strong class="win">'.$wins.'</strong></div>
                <div class="kpi"><span>Derrotas</span><strong class="loss">'.$losses.'</strong></div>
                <div class="kpi"><span>Win Rate</span><strong>'.number_format($winRate,1,',','.').'%</strong></div>
                <div class="kpi"><span>K / D</span><strong>'.number_format($kd,2,',','.').'</strong></div>
                <div class="kpi"><span>Objetivos</span><strong>'.(int)$player['Objectives'].'</strong></div>
              </div>
              <h3>Últimas participaciones</h3><div class="table-wrap"><table><thead><tr><th>Fecha</th><th>Evento</th><th>Resultado</th><th>Sesión</th></tr></thead><tbody>';
            foreach($player['Recent'] as $r){
                $html.='<tr><td>'.$e((string)$r['CreatedAt']).'</td><td>'.$e((string)($r['EventName']??'TvT')).'</td><td><span class="badge '.(strtoupper((string)$r['Result'])==='WIN'?'win':'loss').'">'.$e((string)$r['Result']).'</span></td><td><a class="btn" href="/rankings?type=tvt&session='.rawurlencode((string)$r['SessionKey']).'">Ver partida</a></td></tr>';
            }
            $html.='</tbody></table></div></section>';
        }else{
            $html.='<div class="empty">No se encontró actividad TvT para ese personaje.</div>';
        }
    }else{
        $html.='<div class="grid"><section class="panel"><span class="eyebrow">GLOBAL RANKING</span><h3>Top jugadores TvT</h3><div class="rank">';
        $pos=0;
        foreach($ranking as $r){
            $pos++;
            $matchesCount=(int)$r['Matches'];$wins=(int)$r['Wins'];$losses=(int)$r['Losses'];
            $wr=$matchesCount>0?$wins/$matchesCount*100:0;
            $deaths=(int)$r['Deaths'];$kd=$deaths>0?(int)$r['Kills']/$deaths:(float)(int)$r['Kills'];
            $mine=strcasecmp((string)$r['Account'],$accountId)===0?' mine':'';
            $html.='<a class="rank-row'.$mine.'" href="/rankings?type=tvt&player='.rawurlencode((string)$r['CharacterName']).'">
              <div class="rank-pos">#'.$pos.'</div>
              <div class="rank-name"><strong>'.$e((string)$r['CharacterName']).'</strong><small>'.$e((string)$r['Account']).'</small></div>
              <div class="stat"><small>W / L</small><b class="win">'.$wins.'</b> / <b class="loss">'.$losses.'</b></div>
              <div class="stat"><small>Win Rate</small><b>'.number_format($wr,1,',','.').'%</b></div>
              <div class="stat"><small>K / D</small><b>'.number_format($kd,2,',','.').'</b></div>
              <div class="stat"><small>Objetivos</small><b>'.(int)$r['Objectives'].'</b></div>
              <div class="stat"><small>Zen</small><b>'.number_format((int)$r['RewardZen'],0,',','.').'</b></div>
            </a>';
        }
        if(!$ranking) $html.='<div class="empty">Todavía no hay resultados TvT.</div>';
        $html.='</div></section>

        <aside class="panel"><span class="eyebrow">LATEST MATCHES</span><h3>Partidas recientes</h3>';
        foreach($matches as $m){
            $winner=(string)($m['WinnerCharacter']??'');
            $html.='<a class="rank-row" style="grid-template-columns:1fr auto" href="/rankings?type=tvt&session='.rawurlencode((string)$m['SessionKey']).'">
              <div class="rank-name"><strong>'.$e((string)$m['EventName']).'</strong><small>'.$e((string)$m['StartedAt']).' · '.(int)$m['EntryCount'].' jugadores</small></div>
              <div class="stat"><small>Ganador</small><b class="win">'.$e($winner!==''?$winner:'—').'</b></div>
            </a>';
        }
        if(!$matches) $html.='<div class="empty">No hay partidas registradas.</div>';
        $html.='</aside></div>';
    }

    $html.='</div>';

    return ['available'=>true,'html'=>$html];
};
