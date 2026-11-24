<?php
declare(strict_types=1);

require_once __DIR__.'/TvTEventService.php';

return static function(string $hook,array $payload,array $config,array $context): mixed {
    /** @var \Omega\Core\Database $muDb */
    $muDb=$context['mu_db'];
    $service=new OmegaTvTEventService($muDb);

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
.tvt .hero{position:relative;overflow:hidden;border:1px solid #384757;border-radius:14px;padding:24px;background:radial-gradient(circle at 12% 20%,rgba(75,167,255,.18),transparent 32%),radial-gradient(circle at 88% 20%,rgba(255,95,103,.16),transparent 32%),linear-gradient(135deg,#0c1722,#11151c 50%,#211012);margin-bottom:16px}
.tvt .hero:before{content:"TVT";position:absolute;right:22px;top:-28px;font-size:145px;font-weight:900;letter-spacing:-.08em;color:rgba(255,255,255,.035)}
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
@media(max-width:720px){.tvt .kpis{grid-template-columns:1fr 1fr}.tvt .participants{grid-template-columns:1fr}.tvt .rank-row{grid-template-columns:32px 1fr 1fr}.tvt .rank-row .stat:nth-child(n+4){display:none}.tvt .event{grid-template-columns:1fr}.tvt .hero:before{font-size:95px}}
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

    if($hook==='admin.panel'){
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

        return $styles().'<div class="tvt">
          <div class="hero"><span class="eyebrow">EVENT MODULE DIAGNOSTICS</span><h2>🔵⚔️🔴 Evento TvT PRO</h2><p class="muted">Lectura directa de TvTMatch, TvTResult y TvTAction. El módulo no altera datos del GameServer.</p></div>
          <div class="kpis">'.$cards.'</div>
          <div class="kpis">
            <div class="kpi"><span>Partidas</span><strong>'.number_format((int)$overview['matches'],0,',','.').'</strong></div>
            <div class="kpi"><span>Resultados</span><strong>'.number_format((int)$overview['results'],0,',','.').'</strong></div>
            <div class="kpi"><span>Jugadores</span><strong>'.number_format((int)$overview['players'],0,',','.').'</strong></div>
            <div class="kpi"><span>Kills</span><strong>'.number_format((int)$overview['kills'],0,',','.').'</strong></div>
            <div class="kpi"><span>Objetivos</span><strong>'.number_format((int)$overview['objectives'],0,',','.').'</strong></div>
            <div class="kpi"><span>Zen entregado</span><strong>'.number_format((int)$overview['zen'],0,',','.').'</strong></div>
          </div>'.
          ($missingRows!==''?'<div class="panel"><h3>Columnas faltantes</h3><div class="table-wrap"><table><thead><tr><th>Tabla</th><th>Faltantes</th></tr></thead><tbody>'.$missingRows.'</tbody></table></div></div>':'').
          '<div class="panel" style="margin-top:14px"><h3>Modo de integración</h3><p class="muted">READ ONLY · No ejecuta INSERT, UPDATE ni DELETE sobre TvTMatch, TvTResult o TvTAction.</p></div>
        </div>';
    }

    if($hook!=='user.page') return null;

    $accountId=trim((string)($payload['account_id']??''));
    if($accountId==='') throw new RuntimeException('No se recibió una cuenta autenticada.');

    if(!$service->ready()){
        return [
            'title'=>'Eventos · TvT',
            'html'=>$styles().'<div class="tvt"><div class="empty"><h3>TvT no está disponible</h3><p>Falta alguna de las tablas o columnas requeridas: <code>TvTMatch</code>, <code>TvTResult</code> y <code>TvTAction</code>.</p></div></div>'
        ];
    }

    $query=(array)($payload['query']??[]);
    $sessionKey=trim((string)($query['session']??''));
    $playerName=trim((string)($query['player']??''));

    $recentLimit=max(5,min(100,(int)($config['recent_matches']??20)));
    $rankingLimit=max(10,min(100,(int)($config['ranking_limit']??30)));
    $timelineLimit=max(20,min(300,(int)($config['timeline_limit']??100)));
    $showRewards=$cfgBool('show_rewards',true);
    $showObjectives=$cfgBool('show_objectives',true);

    $overview=$service->overview();
    $ranking=$service->ranking($rankingLimit);
    $matches=$service->recentMatches($recentLimit);

    $html=$styles().'<div class="tvt">
      <div class="hero">
        <span class="eyebrow">EVENT CENTER · TEAM VS TEAM</span>
        <h2><span class="blue">BLUE</span> ⚔️ <span class="red">RED</span> · TvT</h2>
        <p class="muted">Historial, ranking y estadísticas reales del evento registrados por el GameServer.</p>
      </div>

      <div class="kpis">
        <div class="kpi"><span>Partidas</span><strong>'.number_format((int)$overview['matches'],0,',','.').'</strong></div>
        <div class="kpi"><span>Participaciones</span><strong>'.number_format((int)$overview['results'],0,',','.').'</strong></div>
        <div class="kpi"><span>Jugadores</span><strong>'.number_format((int)$overview['players'],0,',','.').'</strong></div>
        <div class="kpi"><span>Kills</span><strong>'.number_format((int)$overview['kills'],0,',','.').'</strong></div>'.
        ($showObjectives?'<div class="kpi"><span>Objetivos</span><strong>'.number_format((int)$overview['objectives'],0,',','.').'</strong></div>':'').
        ($showRewards?'<div class="kpi"><span>Zen entregado</span><strong>'.number_format((int)$overview['zen'],0,',','.').'</strong></div>':'').
      '</div>';

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
                <div><a class="btn" href="/account/modules/event-tvt-pro">← Volver</a></div>
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
              <div class="session-head"><div><span class="eyebrow">PLAYER PROFILE</span><h3>'.$e((string)$player['CharacterName']).'</h3><span class="muted">'.$e((string)$player['Account']).'</span></div><a class="btn" href="/account/modules/event-tvt-pro">← Volver</a></div>
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
                $html.='<tr><td>'.$e((string)$r['CreatedAt']).'</td><td>'.$e((string)($r['EventName']??'TvT')).'</td><td><span class="badge '.(strtoupper((string)$r['Result'])==='WIN'?'win':'loss').'">'.$e((string)$r['Result']).'</span></td><td><a class="btn" href="/account/modules/event-tvt-pro?session='.rawurlencode((string)$r['SessionKey']).'">Ver partida</a></td></tr>';
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
            $html.='<a class="rank-row'.$mine.'" href="/account/modules/event-tvt-pro?player='.rawurlencode((string)$r['CharacterName']).'">
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
            $html.='<a class="rank-row" style="grid-template-columns:1fr auto" href="/account/modules/event-tvt-pro?session='.rawurlencode((string)$m['SessionKey']).'">
              <div class="rank-name"><strong>'.$e((string)$m['EventName']).'</strong><small>'.$e((string)$m['StartedAt']).' · '.(int)$m['EntryCount'].' jugadores</small></div>
              <div class="stat"><small>Ganador</small><b class="win">'.$e($winner!==''?$winner:'—').'</b></div>
            </a>';
        }
        if(!$matches) $html.='<div class="empty">No hay partidas registradas.</div>';
        $html.='</aside></div>';
    }

    $html.='</div>';

    return ['title'=>'Eventos · TvT','html'=>$html];
};
