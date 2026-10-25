<?php
declare(strict_types=1);

/**
 * Omega MU CMS
 * Guild Directory Professional v1.0.0
 *
 * READ ONLY:
 * - Guild
 * - GuildMember
 * - Character
 *
 * Total Reset:
 * SUM(Character.ResetCount) de todos los personajes presentes en GuildMember.
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

    $cfgBool = static function(string $key, bool $default=false) use ($config): bool {
        if(!array_key_exists($key,$config)) return $default;
        $v=$config[$key];
        if(is_bool($v)) return $v;
        return in_array(strtolower((string)$v),['1','true','yes','on'],true);
    };

    $tableExists = static function(string $table) use ($db): bool {
        $row=$db->fetchOne(
            "SELECT COUNT(*) AS total
               FROM INFORMATION_SCHEMA.TABLES
              WHERE TABLE_SCHEMA='dbo'
                AND TABLE_TYPE='BASE TABLE'
                AND TABLE_NAME=:table",
            ['table'=>$table]
        );
        return (int)($row['total']??0)>0;
    };

    $columnMap = static function(string $table) use ($db): array {
        $rows=$db->fetchAll(
            "SELECT COLUMN_NAME
               FROM INFORMATION_SCHEMA.COLUMNS
              WHERE TABLE_SCHEMA='dbo'
                AND TABLE_NAME=:table
              ORDER BY ORDINAL_POSITION",
            ['table'=>$table]
        );

        $map=[];
        foreach($rows as $row){
            $name=(string)$row['COLUMN_NAME'];
            $map[strtolower($name)]=$name;
        }
        return $map;
    };

    $findCol = static function(array $map,array $candidates): ?string {
        foreach($candidates as $candidate){
            $k=strtolower($candidate);
            if(isset($map[$k])) return $map[$k];
        }
        return null;
    };

    $binaryToBytes = static function(mixed $value): array {
        if($value===null) return [];

        if(is_resource($value)){
            $data=stream_get_contents($value);
            if($data===false) return [];
        }elseif(is_string($value)){
            $data=$value;
        }else{
            return [];
        }

        if($data==='') return [];

        $bytes=[];
        for($i=0,$len=strlen($data);$i<$len;$i++){
            $bytes[]=ord($data[$i]);
        }
        return $bytes;
    };

    $guildMarkHtml = static function(mixed $mark,string $name='Guild') use($binaryToBytes,$e): string {
        $bytes=$binaryToBytes($mark);

        // MU Guild Mark clásico: 8x8 píxeles, 4 bits por píxel, 32 bytes.
        // Si el driver devuelve HEX ASCII, se normaliza.
        if(count($bytes)>32){
            $raw='';
            foreach($bytes as $b) $raw.=chr($b);

            $hex=preg_replace('/\s+/','',$raw);
            if($hex!==null && strlen($hex)>=64 && preg_match('/^[0-9A-Fa-f]+$/',$hex)){
                $decoded=@hex2bin(substr($hex,0,64));
                if($decoded!==false){
                    $bytes=[];
                    for($i=0;$i<strlen($decoded);$i++) $bytes[]=ord($decoded[$i]);
                }
            }
        }

        if(count($bytes)<32){
            return '<div class="gd-mark gd-mark-empty" title="'.$e($name).'">🏰</div>';
        }

        $palette=[
            '#00000000', '#111111', '#3b3b3b', '#737373',
            '#bdbdbd', '#ffffff', '#7b1d1d', '#d44b3d',
            '#5a360c', '#d19c35', '#184a1f', '#42a84f',
            '#153d66', '#3b7fc4', '#522267', '#a95bd0'
        ];

        $html='<div class="gd-mark" title="'.$e($name).'">';
        $pixel=0;

        for($i=0;$i<32;$i++){
            $byte=$bytes[$i];
            $nibbles=[($byte>>4)&0x0F,$byte&0x0F];

            foreach($nibbles as $n){
                if($pixel>=64) break;
                $color=$palette[$n]??'#00000000';
                if($n===0){
                    $html.='<span class="gd-px gd-transparent"></span>';
                }else{
                    $html.='<span class="gd-px" style="background:'.$color.'"></span>';
                }
                $pixel++;
            }
        }

        $html.='</div>';
        return $html;
    };

    $styles = static function(): string {
        return <<<'CSS'
<style>
.guilddir{--gd-bg:#0c1117;--gd-panel:#111923;--gd-panel2:#16212c;--gd-border:#2c3c4a;--gd-gold:#d8b15c;--gd-red:#b9524c;--gd-green:#70d69d;color:#e8eef2}
.guilddir *{box-sizing:border-box}
.guilddir .gd-hero{position:relative;overflow:hidden;border:1px solid #403727;border-radius:15px;padding:26px;background:radial-gradient(circle at 80% 20%,rgba(216,177,92,.16),transparent 36%),linear-gradient(135deg,#17120d,#0b1218 55%,#101a23);margin-bottom:18px}
.guilddir .gd-hero:after{content:"🏰";position:absolute;right:34px;top:-18px;font-size:145px;opacity:.055}
.guilddir .gd-eyebrow{font-size:11px;letter-spacing:.16em;font-weight:800;color:#94a6b4}
.guilddir h2,.guilddir h3{color:#f1d486;margin:4px 0}.guilddir .gd-hero h2{font-size:31px}
.guilddir .gd-muted{color:#8fa2b0}
.guilddir .gd-kpis{display:grid;grid-template-columns:repeat(4,minmax(140px,1fr));gap:10px;margin:16px 0}
.guilddir .gd-kpi{background:linear-gradient(180deg,var(--gd-panel2),var(--gd-panel));border:1px solid var(--gd-border);border-radius:10px;padding:14px}
.guilddir .gd-kpi span{display:block;color:#8fa2b0;font-size:11px;text-transform:uppercase;letter-spacing:.07em}
.guilddir .gd-kpi strong{display:block;font-size:26px;margin-top:4px;color:#f2dfad}
.guilddir .gd-toolbar{display:flex;gap:10px;flex-wrap:wrap;align-items:end;background:var(--gd-panel);border:1px solid var(--gd-border);padding:13px;border-radius:10px;margin:15px 0}
.guilddir .gd-toolbar label{display:grid;gap:5px;color:#9eb0bd;font-size:12px}
.guilddir input,.guilddir select{background:#081019;color:#eaf0f3;border:1px solid #344a5a;border-radius:6px;padding:8px 10px}
.guilddir .gd-btn{display:inline-flex;align-items:center;justify-content:center;text-decoration:none;border:1px solid #66542f;background:#2b2519;color:#f0d47d;border-radius:6px;padding:8px 13px;font-weight:700}
.guilddir .gd-btn:hover{background:#423620}
.guilddir .gd-grid{display:grid;grid-template-columns:repeat(2,minmax(360px,1fr));gap:14px}
.guilddir .gd-card{background:linear-gradient(145deg,#151f29,#0d151d);border:1px solid var(--gd-border);border-radius:12px;padding:15px;position:relative;overflow:hidden}
.guilddir .gd-card:after{content:"";position:absolute;width:130px;height:130px;border-radius:50%;background:radial-gradient(circle,rgba(216,177,92,.08),transparent 70%);right:-35px;bottom:-45px;pointer-events:none}
.guilddir .gd-head{display:grid;grid-template-columns:74px 1fr auto;gap:13px;align-items:center;padding-bottom:12px;border-bottom:1px solid #263541}
.guilddir .gd-mark{width:64px;height:64px;display:grid;grid-template-columns:repeat(8,1fr);grid-template-rows:repeat(8,1fr);background:#070b0f;border:2px solid #8c6c31;border-radius:8px;padding:5px;box-shadow:0 0 0 1px #151515 inset,0 0 18px rgba(216,177,92,.08);image-rendering:pixelated}
.guilddir .gd-px{display:block}.guilddir .gd-transparent{background:transparent!important}
.guilddir .gd-mark-empty{display:flex;align-items:center;justify-content:center;font-size:32px;color:#836f48}
.guilddir .gd-name{font-size:21px;font-weight:900;color:#f3d780}.guilddir .gd-master{font-size:12px;color:#98aab7;margin-top:3px}
.guilddir .gd-score{text-align:right}.guilddir .gd-score span{display:block;font-size:10px;color:#7f93a2;text-transform:uppercase}.guilddir .gd-score b{font-size:19px;color:#d8b15c}
.guilddir .gd-stats{display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin:12px 0}
.guilddir .gd-stat{background:#0a1219;border-radius:7px;padding:9px;border:1px solid #1f2d38}.guilddir .gd-stat span{display:block;color:#8195a4;font-size:10px;text-transform:uppercase}.guilddir .gd-stat b{display:block;margin-top:3px;font-size:17px;color:#ecf1f4}
.guilddir .gd-notice{border-left:3px solid #a48545;background:#111820;padding:10px 11px;border-radius:5px;color:#cbd6dd;font-style:italic;min-height:42px}
.guilddir .gd-members{display:flex;gap:6px;flex-wrap:wrap;margin-top:10px}.guilddir .gd-member{font-size:11px;background:#0a1219;border:1px solid #253541;border-radius:999px;padding:4px 8px;color:#9eb0bc}
.guilddir .gd-empty{padding:28px;text-align:center;border:1px dashed #344956;border-radius:10px;background:#0d151d;color:#90a2af}
.guilddir .gd-note{border:1px solid #57482b;background:rgba(125,95,38,.10);color:#d8c693;border-radius:8px;padding:10px 12px;margin-top:15px}
@media(max-width:1000px){.guilddir .gd-grid{grid-template-columns:1fr}.guilddir .gd-kpis{grid-template-columns:repeat(2,1fr)}}
@media(max-width:620px){.guilddir .gd-head{grid-template-columns:64px 1fr}.guilddir .gd-score{grid-column:1/-1;text-align:left}.guilddir .gd-stats{grid-template-columns:1fr 1fr}.guilddir .gd-kpis{grid-template-columns:1fr 1fr}}
</style>
CSS;
    };

    if($hook==='admin.panel'){
        $guildOk=$tableExists('Guild');
        $memberOk=$tableExists('GuildMember');
        $charOk=$tableExists('Character');

        $guildCols=$guildOk?$columnMap('Guild'):[];
        $memberCols=$memberOk?$columnMap('GuildMember'):[];
        $charCols=$charOk?$columnMap('Character'):[];

        $count=0;
        if($guildOk){
            $r=$db->fetchOne("SELECT COUNT(*) AS total FROM dbo.Guild");
            $count=(int)($r['total']??0);
        }

        $checks=[
            ['Guild','G_Name',$guildCols],
            ['Guild','G_Mark',$guildCols],
            ['Guild','G_Master',$guildCols],
            ['Guild','G_Notice',$guildCols],
            ['Guild','G_Score',$guildCols],
            ['GuildMember','Name',$memberCols],
            ['GuildMember','G_Name',$memberCols],
            ['Character','Name',$charCols],
            ['Character','ResetCount',$charCols],
        ];

        $rows='';
        foreach($checks as [$table,$column,$map]){
            $ok=isset($map[strtolower($column)]);
            $rows.='<tr><td>'.$e($table).'</td><td><code>'.$e($column).'</code></td><td style="color:'.($ok?'#70d69d':'#ff8e86').'">'.($ok?'✓ Detectada':'✕ Falta').'</td></tr>';
        }

        return $styles().'
        <div class="guilddir">
          <div class="gd-hero">
            <span class="gd-eyebrow">MODULE DIAGNOSTICS</span>
            <h2>🏰 Guild Directory Professional</h2>
            <p class="gd-muted">Directorio de clanes basado en Guild + GuildMember + Character.</p>
          </div>

          <div class="gd-kpis">
            <div class="gd-kpi"><span>Guild</span><strong style="color:'.($guildOk?'#70d69d':'#ff8e86').'">'.($guildOk?'ONLINE':'MISSING').'</strong></div>
            <div class="gd-kpi"><span>GuildMember</span><strong style="color:'.($memberOk?'#70d69d':'#ff8e86').'">'.($memberOk?'ONLINE':'MISSING').'</strong></div>
            <div class="gd-kpi"><span>Character</span><strong style="color:'.($charOk?'#70d69d':'#ff8e86').'">'.($charOk?'ONLINE':'MISSING').'</strong></div>
            <div class="gd-kpi"><span>Clanes</span><strong>'.number_format($count,0,',','.').'</strong></div>
          </div>

          <div class="gd-note">
            <strong>Total Resets:</strong> SUM(Character.ResetCount) de todos los personajes listados en GuildMember para cada G_Name.
            El módulo es completamente READ ONLY.
          </div>

          <div style="overflow:auto;margin-top:15px">
            <table style="width:100%;border-collapse:collapse">
              <thead><tr><th style="text-align:left;padding:8px">Tabla</th><th style="text-align:left;padding:8px">Columna</th><th style="text-align:left;padding:8px">Estado</th></tr></thead>
              <tbody>'.$rows.'</tbody>
            </table>
          </div>
        </div>';
    }

    if($hook!=='user.page'){
        return null;
    }

    if(!$tableExists('Guild') || !$tableExists('GuildMember') || !$tableExists('Character')){
        return [
            'title'=>'Clanes',
            'html'=>$styles().'<div class="guilddir"><div class="gd-empty"><h3>🏰 Sistema de Guild incompleto</h3><p>Se requieren las tablas Guild, GuildMember y Character.</p></div></div>'
        ];
    }

    $gcols=$columnMap('Guild');
    $mcols=$columnMap('GuildMember');
    $ccols=$columnMap('Character');

    $gName=$findCol($gcols,['G_Name','GuildName']);
    $gMark=$findCol($gcols,['G_Mark','GuildMark']);
    $gMaster=$findCol($gcols,['G_Master','GuildMaster','Master']);
    $gNotice=$findCol($gcols,['G_Notice','Notice']);
    $gScore=$findCol($gcols,['G_Score','Score']);

    $mName=$findCol($mcols,['Name','CharacterName']);
    $mGuild=$findCol($mcols,['G_Name','GuildName']);

    $cName=$findCol($ccols,['Name','CharacterName']);
    $cReset=$findCol($ccols,['ResetCount','Resets','Reset']);

    if(!$gName || !$mName || !$mGuild || !$cName || !$cReset){
        throw new RuntimeException('La estructura de Guild/GuildMember/Character no contiene las columnas mínimas requeridas.');
    }

    $query=(array)($payload['query']??[]);
    $search=trim((string)($query['q']??''));
    $sort=trim((string)($query['sort']??($config['default_sort']??'resets')));

    if(!in_array($sort,['resets','members','score','name'],true)){
        $sort='resets';
    }

    $limit=max(10,min(200,(int)($config['page_limit']??50)));

    $params=[];
    $where='1=1';
    if($search!==''){
        $where.=" AND (g.[{$gName}] LIKE :q";
        if($gMaster) $where.=" OR g.[{$gMaster}] LIKE :q";
        if($gNotice) $where.=" OR g.[{$gNotice}] LIKE :q";
        $where.=")";
        $params['q']='%'.$search.'%';
    }

    $order=match($sort){
        'members'=>'MemberCount DESC, TotalResets DESC, GuildName ASC',
        'score'=>'GuildScore DESC, TotalResets DESC, GuildName ASC',
        'name'=>'GuildName ASC',
        default=>'TotalResets DESC, MemberCount DESC, GuildName ASC',
    };

    $scoreSelect=$gScore ? "ISNULL(g.[{$gScore}],0)" : "0";
    $markSelect=$gMark ? "g.[{$gMark}]" : "NULL";
    $masterSelect=$gMaster ? "g.[{$gMaster}]" : "''";
    $noticeSelect=$gNotice ? "g.[{$gNotice}]" : "''";

    $sql="
        SELECT TOP {$limit}
            g.[{$gName}] AS GuildName,
            {$markSelect} AS GuildMark,
            {$masterSelect} AS GuildMaster,
            {$noticeSelect} AS GuildNotice,
            {$scoreSelect} AS GuildScore,
            COUNT(gm.[{$mName}]) AS MemberCount,
            ISNULL(SUM(ISNULL(c.[{$cReset}],0)),0) AS TotalResets
        FROM dbo.Guild g
        LEFT JOIN dbo.GuildMember gm
            ON gm.[{$mGuild}] = g.[{$gName}]
        LEFT JOIN dbo.Character c
            ON c.[{$cName}] = gm.[{$mName}]
        WHERE {$where}
        GROUP BY
            g.[{$gName}],
            ".($gMark ? "g.[{$gMark}]," : "")."
            ".($gMaster ? "g.[{$gMaster}]," : "")."
            ".($gNotice ? "g.[{$gNotice}]," : "")."
            ".($gScore ? "g.[{$gScore}]" : "g.[{$gName}]")."
        ORDER BY {$order}
    ";

    // Clean possible dangling comma before GROUP ORDER due optional columns.
    $sql=preg_replace('/,\s*\n\s*g\.\['.preg_quote($gName,'/').'\]\s*\n\s*ORDER BY/s',"\n            g.[{$gName}]\n        ORDER BY",$sql) ?: $sql;

    $guilds=$db->fetchAll($sql,$params);

    // Global stats use all guilds, not only current filtered top.
    $stats=$db->fetchOne("
        SELECT
            COUNT(DISTINCT g.[{$gName}]) AS GuildCount,
            COUNT(gm.[{$mName}]) AS MemberCount,
            ISNULL(SUM(ISNULL(c.[{$cReset}],0)),0) AS TotalResets
        FROM dbo.Guild g
        LEFT JOIN dbo.GuildMember gm
            ON gm.[{$mGuild}] = g.[{$gName}]
        LEFT JOIN dbo.Character c
            ON c.[{$cName}] = gm.[{$mName}]
    ");

    $topReset=(int)($guilds[0]['TotalResets']??0);

    // Small member preview, queried only for displayed guilds.
    $memberPreview=[];
    if($cfgBool('show_member_preview',true)){
        foreach($guilds as $g){
            $gn=(string)($g['GuildName']??'');
            if($gn==='') continue;

            $members=$db->fetchAll("
                SELECT TOP 6
                    gm.[{$mName}] AS Name,
                    ISNULL(c.[{$cReset}],0) AS ResetCount
                FROM dbo.GuildMember gm
                LEFT JOIN dbo.Character c
                    ON c.[{$cName}] = gm.[{$mName}]
                WHERE gm.[{$mGuild}]=:guild
                ORDER BY ISNULL(c.[{$cReset}],0) DESC, gm.[{$mName}] ASC
            ",['guild'=>$gn]);

            $memberPreview[$gn]=$members;
        }
    }

    $html=$styles();
    $html.='<div class="guilddir">
      <div class="gd-hero">
        <span class="gd-eyebrow">GUILD DIRECTORY</span>
        <h2>🏰 Clanes del Servidor</h2>
        <p class="gd-muted">Explora todos los clanes, sus maestros, miembros, notice y poder acumulado en resets.</p>
      </div>';

    $html.='<div class="gd-kpis">
        <div class="gd-kpi"><span>Clanes</span><strong>'.number_format((int)($stats['GuildCount']??0),0,',','.').'</strong></div>
        <div class="gd-kpi"><span>Miembros</span><strong>'.number_format((int)($stats['MemberCount']??0),0,',','.').'</strong></div>
        <div class="gd-kpi"><span>Resets acumulados</span><strong>'.number_format((int)($stats['TotalResets']??0),0,',','.').'</strong></div>
        <div class="gd-kpi"><span>Top Guild Resets</span><strong>'.number_format($topReset,0,',','.').'</strong></div>
    </div>';

    $html.='<form method="get" class="gd-toolbar">
        <label>Buscar clan / master / notice
            <input name="q" value="'.$e($search).'" placeholder="Nombre del clan...">
        </label>
        <label>Ordenar por
            <select name="sort">
                <option value="resets" '.($sort==='resets'?'selected':'').'>Total Resets</option>
                <option value="members" '.($sort==='members'?'selected':'').'>Miembros</option>
                <option value="score" '.($sort==='score'?'selected':'').'>Guild Score</option>
                <option value="name" '.($sort==='name'?'selected':'').'>Nombre</option>
            </select>
        </label>
        <button class="gd-btn" type="submit">Aplicar</button>';

    if($search!=='' || $sort!==($config['default_sort']??'resets')){
        $html.='<a class="gd-btn" href="/account/modules/guild-directory-professional">Limpiar</a>';
    }

    $html.='<span class="gd-muted">Mostrando hasta '.$limit.' clanes</span>
    </form>';

    if(!$guilds){
        $html.='<div class="gd-empty"><h3>🏰 No se encontraron clanes</h3><p>No existen coincidencias para el filtro aplicado.</p></div>';
    }else{
        $html.='<div class="gd-grid">';

        foreach($guilds as $guild){
            $name=(string)($guild['GuildName']??'Sin nombre');
            $master=(string)($guild['GuildMaster']??'—');
            $notice=trim((string)($guild['GuildNotice']??''));
            $score=(int)($guild['GuildScore']??0);
            $members=(int)($guild['MemberCount']??0);
            $resets=(int)($guild['TotalResets']??0);

            $html.='<article class="gd-card">
                <div class="gd-head">
                    '.$guildMarkHtml($guild['GuildMark']??null,$name).'
                    <div>
                        <div class="gd-name">'.$e($name).'</div>
                        <div class="gd-master">👑 Guild Master: <strong>'.$e($master!==''?$master:'—').'</strong></div>
                    </div>
                    <div class="gd-score">';

            if($cfgBool('show_score',true)){
                $html.='<span>Guild Score</span><b>'.number_format($score,0,',','.').'</b>';
            }

            $html.='</div>
                </div>

                <div class="gd-stats">
                    <div class="gd-stat"><span>Miembros</span><b>👥 '.number_format($members,0,',','.').'</b></div>
                    <div class="gd-stat"><span>Total Resets</span><b>♻️ '.number_format($resets,0,',','.').'</b></div>
                    <div class="gd-stat"><span>Promedio</span><b>'.($members>0?number_format($resets/$members,1,',','.'):'0').'</b></div>
                </div>';

            if($cfgBool('show_notice',true)){
                $html.='<div class="gd-notice">📜 '.($notice!==''?$e($notice):'<span class="gd-muted">Este clan no tiene notice.</span>').'</div>';
            }

            if($cfgBool('show_member_preview',true) && !empty($memberPreview[$name])){
                $html.='<div class="gd-members">';
                foreach($memberPreview[$name] as $member){
                    $html.='<span class="gd-member">⚔ '.$e((string)$member['Name']).' · R '.number_format((int)$member['ResetCount'],0,',','.').'</span>';
                }
                $html.='</div>';
            }

            $html.='</article>';
        }

        $html.='</div>';
    }

    $html.='<div class="gd-note">
        ♻️ <strong>Total Resets</strong> se calcula en tiempo real sumando <code>Character.ResetCount</code>
        de cada personaje que pertenece al clan mediante <code>GuildMember</code>.
    </div>';

    $html.='</div>';

    return [
        'title'=>'Clanes',
        'html'=>$html
    ];
};
