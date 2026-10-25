<?php
declare(strict_types=1);

/**
 * Omega MU CMS
 * Duel History Professional v1.0.1
 *
 * READ ONLY:
 * - DuelHistory
 * - DuelRanking
 * - Character
 *
 * No INSERT / UPDATE / DELETE sobre la base del GameServer.
 */

return static function(
    string $hook,
    array $payload,
    array $config,
    array $context
): mixed {
    $db = $context['mu_db'];

    $e = static fn(mixed $v): string =>
        htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    $tableExists = static function(string $table) use ($db): bool {
        $row = $db->fetchOne(
            "SELECT COUNT(*) AS total
               FROM INFORMATION_SCHEMA.TABLES
              WHERE TABLE_SCHEMA='dbo'
                AND TABLE_TYPE='BASE TABLE'
                AND TABLE_NAME=:table",
            ['table'=>$table]
        );
        return (int)($row['total'] ?? 0) > 0;
    };

    $columnMap = static function(string $table) use ($db): array {
        $rows = $db->fetchAll(
            "SELECT COLUMN_NAME
               FROM INFORMATION_SCHEMA.COLUMNS
              WHERE TABLE_SCHEMA='dbo'
                AND TABLE_NAME=:table
              ORDER BY ORDINAL_POSITION",
            ['table'=>$table]
        );

        $map = [];
        foreach($rows as $row){
            $name = (string)$row['COLUMN_NAME'];
            $map[strtolower($name)] = $name;
        }
        return $map;
    };

    $findCol = static function(array $map, array $names): ?string {
        foreach($names as $name){
            $k = strtolower($name);
            if(isset($map[$k])) return $map[$k];
        }
        return null;
    };

    $cfgBool = static function(string $key, bool $default=false) use ($config): bool {
        if(!array_key_exists($key,$config)) return $default;
        $v=$config[$key];
        if(is_bool($v)) return $v;
        return in_array(strtolower((string)$v),['1','true','yes','on'],true);
    };

    $historyLimit = max(10, min(200, (int)($config['history_limit'] ?? 50)));

    $formatDuration = static function(mixed $seconds): string {
        $s=max(0,(int)$seconds);
        if($s<60) return $s.'s';
        $m=intdiv($s,60);
        $r=$s%60;
        if($m<60) return $m.'m '.str_pad((string)$r,2,'0',STR_PAD_LEFT).'s';
        $h=intdiv($m,60);
        $m=$m%60;
        return $h.'h '.str_pad((string)$m,2,'0',STR_PAD_LEFT).'m';
    };

    $classLabel = static function(mixed $class): string {
        $c=(int)$class;
        return match($c){
            0,1,2,3 => 'Dark Wizard',
            16,17,18,19 => 'Dark Knight',
            32,33,34,35 => 'Fairy Elf',
            48,49,50,51 => 'Magic Gladiator',
            64,65,66,67 => 'Dark Lord',
            default => 'Clase '.$c,
        };
    };

    $renderStyles = static function(): string {
        return <<<'CSS'
<style>
.duelx{--dx-bg:#0c1117;--dx-panel:#111923;--dx-panel2:#151f2b;--dx-border:#2a3a4a;--dx-gold:#d5ad55;--dx-red:#cf5c58;--dx-green:#6bd49b;--dx-blue:#6eb7e8;color:#e8eef3}
.duelx *{box-sizing:border-box}
.duelx .dx-hero{position:relative;overflow:hidden;border:1px solid var(--dx-border);border-radius:14px;padding:24px;background:radial-gradient(circle at 80% 15%,rgba(207,92,88,.18),transparent 36%),linear-gradient(135deg,#191117,#0b1219 55%,#101a24);margin-bottom:18px}
.duelx .dx-hero:after{content:"⚔";position:absolute;right:28px;top:-22px;font-size:150px;color:rgba(213,173,85,.08);transform:rotate(-10deg)}
.duelx .dx-eyebrow{font-size:11px;letter-spacing:.16em;color:#91a7b8;font-weight:800}
.duelx h2,.duelx h3{margin:4px 0;color:#f4d98b}
.duelx .dx-hero h2{font-size:30px}
.duelx .dx-muted{color:#8ea2b2}
.duelx .dx-kpis{display:grid;grid-template-columns:repeat(5,minmax(120px,1fr));gap:10px;margin:16px 0}
.duelx .dx-kpi{background:linear-gradient(180deg,var(--dx-panel2),var(--dx-panel));border:1px solid var(--dx-border);border-radius:10px;padding:14px}
.duelx .dx-kpi span{display:block;color:#90a4b4;font-size:11px;text-transform:uppercase;letter-spacing:.08em}
.duelx .dx-kpi strong{display:block;font-size:26px;margin-top:4px;color:#f5e4b1}
.duelx .win{color:var(--dx-green)!important}.duelx .loss{color:#ff827b!important}.duelx .ratio{color:#83c9ef!important}
.duelx .dx-toolbar{display:flex;gap:10px;align-items:end;flex-wrap:wrap;background:var(--dx-panel);border:1px solid var(--dx-border);border-radius:10px;padding:13px;margin:14px 0}
.duelx .dx-toolbar label{display:grid;gap:5px;color:#9fb1bf;font-size:12px}
.duelx select{min-width:180px;background:#081019;color:#e9eef2;border:1px solid #33485b;border-radius:6px;padding:8px}
.duelx .dx-btn{display:inline-flex;align-items:center;justify-content:center;text-decoration:none;border:1px solid #6a5730;background:#2d271b;color:#f4d98b;border-radius:6px;padding:8px 13px;font-weight:700}
.duelx .dx-btn:hover{background:#42361f}
.duelx .dx-section{margin-top:18px}
.duelx .dx-table-wrap{overflow:auto;border:1px solid var(--dx-border);border-radius:10px;background:#0d151e}
.duelx table{width:100%;border-collapse:collapse;min-width:900px}
.duelx th{padding:10px 11px;text-align:left;font-size:11px;text-transform:uppercase;letter-spacing:.06em;color:#8fa4b4;background:#111b25;border-bottom:1px solid var(--dx-border)}
.duelx td{padding:11px;border-bottom:1px solid #1e2c38;vertical-align:middle}
.duelx tr:last-child td{border-bottom:0}
.duelx tbody tr:hover{background:rgba(110,183,232,.05)}
.duelx .dx-result{display:inline-flex;min-width:72px;justify-content:center;border-radius:999px;padding:4px 8px;font-size:11px;font-weight:900}
.duelx .dx-result-win{background:rgba(107,212,155,.11);color:#7ce0a8;border:1px solid rgba(107,212,155,.28)}
.duelx .dx-result-loss{background:rgba(255,130,123,.10);color:#ff918a;border:1px solid rgba(255,130,123,.25)}
.duelx .dx-opponent{font-weight:800;color:#edf3f7}.duelx .dx-you{color:#d8b45e;font-weight:800}
.duelx .dx-score{font-weight:900;font-variant-numeric:tabular-nums}
.duelx .dx-ranking-grid{display:grid;grid-template-columns:repeat(3,minmax(220px,1fr));gap:12px}
.duelx .dx-rank-card{border:1px solid var(--dx-border);border-radius:10px;background:linear-gradient(150deg,#151e28,#0d151d);padding:15px}
.duelx .dx-rank-head{display:flex;justify-content:space-between;gap:8px;align-items:start;border-bottom:1px solid #283744;padding-bottom:10px;margin-bottom:10px}
.duelx .dx-rank-name{font-size:18px;font-weight:900;color:#f0d47e}.duelx .dx-class{font-size:11px;color:#8ca0af}
.duelx .dx-rank-stats{display:grid;grid-template-columns:repeat(3,1fr);gap:7px}
.duelx .dx-rank-stats div{background:#0a1219;border-radius:6px;padding:8px}
.duelx .dx-rank-stats span{font-size:10px;color:#7f95a6;display:block}.duelx .dx-rank-stats b{font-size:15px}
.duelx .dx-empty{padding:26px;text-align:center;border:1px dashed #34495b;border-radius:10px;background:#0d151d;color:#8da2b1}
.duelx .dx-note{border:1px solid #59482a;background:rgba(130,99,37,.10);color:#d7c594;border-radius:8px;padding:10px 12px;margin-top:12px}
@media(max-width:980px){.duelx .dx-kpis{grid-template-columns:repeat(3,1fr)}.duelx .dx-ranking-grid{grid-template-columns:1fr 1fr}}
@media(max-width:650px){.duelx .dx-kpis{grid-template-columns:1fr 1fr}.duelx .dx-ranking-grid{grid-template-columns:1fr}}
</style>
CSS;
    };

    if($hook === 'admin.panel'){
        $historyOk=$tableExists('DuelHistory');
        $rankingOk=$tableExists('DuelRanking');
        $hcols=$historyOk ? $columnMap('DuelHistory') : [];
        $rcols=$rankingOk ? $columnMap('DuelRanking') : [];

        $hcount=0;
        $rcount=0;
        if($historyOk){
            $row=$db->fetchOne("SELECT COUNT(*) AS total FROM dbo.DuelHistory");
            $hcount=(int)($row['total']??0);
        }
        if($rankingOk){
            $row=$db->fetchOne("SELECT COUNT(*) AS total FROM dbo.DuelRanking");
            $rcount=(int)($row['total']??0);
        }

        $requiredHistory=[
            'WinnerAccount','WinnerName','LoserAccount','LoserName',
            'WinnerClass','LoserClass','WinnerLevel','LoserLevel',
            'WinnerReset','LoserReset','ArenaID','MapNumber',
            'DurationSeconds','WinnerKills','LoserKills',
            'KillsToWin','FinishReason','DuelDate'
        ];

        $rows='';
        foreach($requiredHistory as $col){
            $ok=isset($hcols[strtolower($col)]);
            $rows.='<tr><td><code>'.$e($col).'</code></td><td style="color:'.
                ($ok?'#70d79e':'#ff9b83').'">'.($ok?'✓ Detectada':'✕ Falta').'</td></tr>';
        }

        return $renderStyles().'
        <div class="duelx">
          <div class="dx-hero">
            <span class="dx-eyebrow">MODULE DIAGNOSTICS</span>
            <h2>⚔️ Duel History Professional</h2>
            <p class="dx-muted">Diagnóstico de las tablas PvP detectadas en MuOnline.</p>
          </div>

          <div class="dx-kpis">
            <div class="dx-kpi"><span>DuelHistory</span><strong class="'.($historyOk?'win':'loss').'">'.($historyOk?'ONLINE':'MISSING').'</strong></div>
            <div class="dx-kpi"><span>Registros</span><strong>'.number_format($hcount,0,',','.').'</strong></div>
            <div class="dx-kpi"><span>DuelRanking</span><strong class="'.($rankingOk?'win':'loss').'">'.($rankingOk?'ONLINE':'MISSING').'</strong></div>
            <div class="dx-kpi"><span>Ranking rows</span><strong>'.number_format($rcount,0,',','.').'</strong></div>
            <div class="dx-kpi"><span>Modo</span><strong class="ratio">READ ONLY</strong></div>
          </div>

          <div class="dx-section">
            <h3>Columnas DuelHistory esperadas</h3>
            <div class="dx-table-wrap">
              <table><thead><tr><th>Columna</th><th>Estado</th></tr></thead><tbody>'.$rows.'</tbody></table>
            </div>
          </div>

          <div class="dx-note">
            🔒 El módulo no modifica DuelHistory, DuelRanking ni Character.
            Solo ejecuta SELECT para mostrar estadísticas e historial.
          </div>
        </div>';
    }

    if($hook !== 'user.page'){
        return null;
    }

    $accountId=trim((string)($payload['account_id'] ?? ''));
    if($accountId===''){
        throw new RuntimeException('No se recibió una cuenta autenticada.');
    }

    if(!$tableExists('DuelHistory')){
        return [
            'title'=>'Duel History',
            'html'=>$renderStyles().'
                <div class="duelx">
                    <div class="dx-empty">
                        <h3>⚔️ DuelHistory no está instalado</h3>
                        <p>La tabla <code>DuelHistory</code> no existe en la base de datos actual.</p>
                    </div>
                </div>'
        ];
    }

    $cols=$columnMap('DuelHistory');

    $winnerAccount=$findCol($cols,['WinnerAccount']);
    $winnerName=$findCol($cols,['WinnerName']);
    $loserAccount=$findCol($cols,['LoserAccount']);
    $loserName=$findCol($cols,['LoserName']);

    if(!$winnerName || !$loserName){
        throw new RuntimeException('DuelHistory no contiene WinnerName/LoserName.');
    }

    $characters=$db->fetchAll(
        "SELECT Name FROM Character WHERE AccountID=:account ORDER BY Name ASC",
        ['account'=>$accountId]
    );
    $ownedNames=array_values(array_filter(array_map(
        static fn(array $r): string => trim((string)($r['Name']??'')),
        $characters
    )));

    $query=(array)($payload['query']??[]);
    $filterChar=trim((string)($query['character']??''));

    if($filterChar!=='' && !in_array($filterChar,$ownedNames,true)){
        $filterChar='';
    }

    $where='';
    $params=[];

    if($filterChar!==''){
        // PDO_SQLSRV / ODBC Driver 18 no siempre permite reutilizar el mismo
        // parámetro nombrado más de una vez dentro de la misma sentencia.
        $where="([{$winnerName}]=:char_winner OR [{$loserName}]=:char_loser)";
        $params['char_winner']=$filterChar;
        $params['char_loser']=$filterChar;
    }elseif($winnerAccount && $loserAccount){
        $where="([{$winnerAccount}]=:account_winner OR [{$loserAccount}]=:account_loser)";
        $params['account_winner']=$accountId;
        $params['account_loser']=$accountId;
    }else{
        if(!$ownedNames){
            $where='1=0';
        }else{
            $winnerParams=[];
            $loserParams=[];

            foreach($ownedNames as $i=>$name){
                $winnerKey='wn'.$i;
                $loserKey='ln'.$i;

                $params[$winnerKey]=$name;
                $params[$loserKey]=$name;

                $winnerParams[]=':'.$winnerKey;
                $loserParams[]=':'.$loserKey;
            }

            $winnerIn=implode(',',$winnerParams);
            $loserIn=implode(',',$loserParams);

            $where="([{$winnerName}] IN ({$winnerIn}) OR [{$loserName}] IN ({$loserIn}))";
        }
    }

    $duelDate=$findCol($cols,['DuelDate','CreatedAt','LastDuelDate']);
    $order=$duelDate ? "[{$duelDate}] DESC" : "[{$winnerName}] ASC";

    $history=$db->fetchAll(
        "SELECT TOP {$historyLimit} * FROM dbo.DuelHistory WHERE {$where} ORDER BY {$order}",
        $params
    );

    // Build adaptive column aliases.
    $col = static function(array $row, array $map, array $names) use ($findCol): mixed {
        $c=$findCol($map,$names);
        return $c!==null ? ($row[$c]??null) : null;
    };

    // Accurate summary for all rows in the current filter.
    $total=0;$wins=0;$losses=0;$kills=0;$deaths=0;$durationTotal=0;$durationCount=0;
    $summaryRows=$db->fetchAll(
        "SELECT * FROM dbo.DuelHistory WHERE {$where}",
        $params
    );

    foreach($summaryRows as $row){
        $wn=(string)$col($row,$cols,['WinnerName']);
        $ln=(string)$col($row,$cols,['LoserName']);
        $isWin=$filterChar!=='' ? strcasecmp($wn,$filterChar)===0 : in_array($wn,$ownedNames,true);
        $isLoss=$filterChar!=='' ? strcasecmp($ln,$filterChar)===0 : in_array($ln,$ownedNames,true);

        if(!$isWin && !$isLoss) continue;

        $total++;
        if($isWin) $wins++;
        if($isLoss) $losses++;

        $wk=(int)($col($row,$cols,['WinnerKills'])??0);
        $lk=(int)($col($row,$cols,['LoserKills'])??0);

        if($isWin){
            $kills+=$wk;
            $deaths+=$lk;
        }else{
            $kills+=$lk;
            $deaths+=$wk;
        }

        $dur=$col($row,$cols,['DurationSeconds']);
        if($dur!==null && is_numeric($dur)){
            $durationTotal+=(int)$dur;
            $durationCount++;
        }
    }

    $winRate=$total>0 ? ($wins/$total)*100 : 0.0;
    $kd=$deaths>0 ? $kills/$deaths : (float)$kills;
    $avgDuration=$durationCount>0 ? (int)round($durationTotal/$durationCount) : 0;

    // DuelRanking cards for characters owned by the current account.
    $rankingCards=[];
    if($cfgBool('show_ranking_cards',true) && $tableExists('DuelRanking')){
        $rcols=$columnMap('DuelRanking');
        $rChar=$findCol($rcols,['CharacterName','Name']);
        $rAccount=$findCol($rcols,['AccountID','AccountId']);

        if($rChar){
            if($filterChar!==''){
                $rankingCards=$db->fetchAll(
                    "SELECT TOP 10 * FROM dbo.DuelRanking WHERE [{$rChar}]=:char ORDER BY [{$rChar}]",
                    ['char'=>$filterChar]
                );
            }elseif($rAccount){
                $rankingCards=$db->fetchAll(
                    "SELECT TOP 10 * FROM dbo.DuelRanking WHERE [{$rAccount}]=:account ORDER BY [{$rChar}]",
                    ['account'=>$accountId]
                );
            }elseif($ownedNames){
                $rp=[];
                $bind=[];
                foreach($ownedNames as $i=>$name){
                    $key='r'.$i;
                    $bind[$key]=$name;
                    $rp[]=':'.$key;
                }
                $rankingCards=$db->fetchAll(
                    "SELECT TOP 10 * FROM dbo.DuelRanking WHERE [{$rChar}] IN (".implode(',',$rp).") ORDER BY [{$rChar}]",
                    $bind
                );
            }
        }
    }

    $html=$renderStyles();
    $html.='<div class="duelx">';
    $html.='<div class="dx-hero">
        <span class="dx-eyebrow">PVP CENTER · MY ACCOUNT</span>
        <h2>⚔️ Duel History</h2>
        <p class="dx-muted">Tu historial real de duelos, rivales y rendimiento PvP registrado por el GameServer.</p>
    </div>';

    $html.='<div class="dx-kpis">
        <div class="dx-kpi"><span>Duelos</span><strong>'.number_format($total,0,',','.').'</strong></div>
        <div class="dx-kpi"><span>Victorias</span><strong class="win">'.number_format($wins,0,',','.').'</strong></div>
        <div class="dx-kpi"><span>Derrotas</span><strong class="loss">'.number_format($losses,0,',','.').'</strong></div>
        <div class="dx-kpi"><span>Win Rate</span><strong class="ratio">'.number_format($winRate,1,',','.').'%</strong></div>
        <div class="dx-kpi"><span>K / D</span><strong>'.number_format($kd,2,',','.').'</strong></div>
    </div>';

    $html.='<form class="dx-toolbar" method="get">
        <label>Personaje
            <select name="character">
                <option value="">Todos mis personajes</option>';

    foreach($ownedNames as $name){
        $html.='<option value="'.$e($name).'" '.($filterChar===$name?'selected':'').'>'.$e($name).'</option>';
    }

    $html.='</select>
        </label>
        <button class="dx-btn" type="submit">Aplicar filtro</button>';

    if($filterChar!==''){
        $html.='<a class="dx-btn" href="/account/modules/duel-history-professional">Limpiar</a>';
    }

    $html.='<span class="dx-muted">Mostrando hasta '.$historyLimit.' duelos recientes</span>
    </form>';

    if($rankingCards){
        $rcols=$columnMap('DuelRanking');
        $html.='<div class="dx-section"><span class="dx-eyebrow">DUEL RANKING</span><h3>Estadísticas acumuladas</h3><div class="dx-ranking-grid">';

        foreach($rankingCards as $r){
            $get=static function(array $names) use($r,$rcols,$findCol): mixed {
                $c=$findCol($rcols,$names);
                return $c!==null ? ($r[$c]??null) : null;
            };

            $name=(string)($get(['CharacterName','Name'])??'Personaje');
            $class=$get(['Class']);
            $duels=(int)($get(['Duels'])??0);
            $rwins=(int)($get(['Wins'])??0);
            $rloss=(int)($get(['Losses'])??0);
            $draws=(int)($get(['Draws'])??0);
            $rkills=(int)($get(['Kills'])??0);
            $rdeaths=(int)($get(['Deaths'])??0);
            $streak=(int)($get(['CurrentStreak'])??0);
            $best=(int)($get(['BestStreak'])??0);
            $rating=(int)($get(['Rating'])??0);
            $rrate=$duels>0 ? ($rwins/$duels)*100 : 0;

            $html.='<article class="dx-rank-card">
                <div class="dx-rank-head">
                    <div><div class="dx-rank-name">'.$e($name).'</div><div class="dx-class">'.$e($classLabel($class)).'</div></div>
                    <div><span class="dx-eyebrow">RATING</span><strong>'.$rating.'</strong></div>
                </div>
                <div class="dx-rank-stats">
                    <div><span>Duelos</span><b>'.$duels.'</b></div>
                    <div><span>Wins</span><b class="win">'.$rwins.'</b></div>
                    <div><span>Loss</span><b class="loss">'.$rloss.'</b></div>
                    <div><span>Draws</span><b>'.$draws.'</b></div>
                    <div><span>Win Rate</span><b>'.number_format($rrate,1,',','.').'%</b></div>
                    <div><span>K / D</span><b>'.$rkills.' / '.$rdeaths.'</b></div>
                    <div><span>Racha</span><b>'.$streak.'</b></div>
                    <div><span>Mejor</span><b>'.$best.'</b></div>
                    <div><span>Nivel</span><b>'.(int)($get(['Level'])??0).'</b></div>
                </div>
            </article>';
        }

        $html.='</div></div>';
    }

    $html.='<div class="dx-section"><span class="dx-eyebrow">RECENT BATTLES</span><h3>Últimos duelos</h3>';

    if(!$history){
        $html.='<div class="dx-empty"><h3>⚔️ Sin duelos registrados</h3><p>Cuando tu cuenta participe en duelos, aparecerán aquí.</p></div>';
    }else{
        $html.='<div class="dx-table-wrap"><table><thead><tr>
            <th>Resultado</th>
            <th>Personaje</th>
            <th>Rival</th>
            <th>Marcador</th>
            <th>Niveles</th>';

        if($cfgBool('show_map',true)) $html.='<th>Mapa / Arena</th>';
        if($cfgBool('show_duration',true)) $html.='<th>Duración</th>';
        if($cfgBool('show_finish_reason',true)) $html.='<th>Final</th>';

        $html.='<th>Fecha</th></tr></thead><tbody>';

        foreach($history as $row){
            $wn=(string)$col($row,$cols,['WinnerName']);
            $ln=(string)$col($row,$cols,['LoserName']);
            $isWin=$filterChar!=='' ? strcasecmp($wn,$filterChar)===0 : in_array($wn,$ownedNames,true);

            $you=$isWin ? $wn : $ln;
            $opponent=$isWin ? $ln : $wn;

            $yourKills=(int)($isWin
                ? ($col($row,$cols,['WinnerKills'])??0)
                : ($col($row,$cols,['LoserKills'])??0));

            $oppKills=(int)($isWin
                ? ($col($row,$cols,['LoserKills'])??0)
                : ($col($row,$cols,['WinnerKills'])??0));

            $yourLevel=(int)($isWin
                ? ($col($row,$cols,['WinnerLevel'])??0)
                : ($col($row,$cols,['LoserLevel'])??0));

            $oppLevel=(int)($isWin
                ? ($col($row,$cols,['LoserLevel'])??0)
                : ($col($row,$cols,['WinnerLevel'])??0));

            $map=$col($row,$cols,['MapNumber']);
            $arena=$col($row,$cols,['ArenaID']);
            $duration=$col($row,$cols,['DurationSeconds']);
            $reason=$col($row,$cols,['FinishReason']);
            $date=$col($row,$cols,['DuelDate','CreatedAt','LastDuelDate']);

            $html.='<tr>
                <td><span class="dx-result '.($isWin?'dx-result-win':'dx-result-loss').'">'.($isWin?'VICTORIA':'DERROTA').'</span></td>
                <td class="dx-you">'.$e($you).'</td>
                <td class="dx-opponent">'.$e($opponent).'</td>
                <td class="dx-score">'.$yourKills.' — '.$oppKills.'</td>
                <td>Lv. '.$yourLevel.' / '.$oppLevel.'</td>';

            if($cfgBool('show_map',true)){
                $mapText='Map '.($map!==null?$map:'—');
                if($arena!==null && $arena!=='') $mapText.=' · Arena '.$arena;
                $html.='<td>'.$e($mapText).'</td>';
            }

            if($cfgBool('show_duration',true)){
                $html.='<td>'.($duration!==null?$e($formatDuration($duration)):'—').'</td>';
            }

            if($cfgBool('show_finish_reason',true)){
                $html.='<td>'.$e($reason!==null && $reason!==''?$reason:'—').'</td>';
            }

            $html.='<td>'.$e($date!==null?$date:'—').'</td></tr>';
        }

        $html.='</tbody></table></div>';
    }

    if($avgDuration>0){
        $html.='<div class="dx-note">⏱ Duración promedio de tus duelos: <strong>'.$e($formatDuration($avgDuration)).'</strong> · Kills acumuladas: <strong>'.$kills.'</strong> · Deaths: <strong>'.$deaths.'</strong></div>';
    }

    $html.='</div></div>';

    return [
        'title'=>'Duel History',
        'html'=>$html
    ];
};
