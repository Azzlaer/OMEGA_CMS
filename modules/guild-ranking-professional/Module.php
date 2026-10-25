<?php
declare(strict_types=1);

/**
 * Omega MU CMS
 * Guild Ranking Professional v3.0.0
 *
 * No agrega páginas a Mi Cuenta.
 * No modifica el core.
 *
 * Cuando el visitante abre:
 *   /rankings?type=guilds
 *
 * el hook site.head genera el ranking profesional server-side y reemplaza
 * únicamente el bloque de resultados del ranking clásico.
 *
 * Desactivar/desinstalar el módulo = volver al ranking clásico.
 */

return static function(
    string $hook,
    array $payload,
    array $config,
    array $context
): mixed {
    $db=$context['mu_db'];

    $e=static fn(mixed $v): string =>
        htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');

    $cfgBool=static function(string $key,bool $default=false) use($config): bool {
        if(!array_key_exists($key,$config)) return $default;
        $v=$config[$key];
        if(is_bool($v)) return $v;
        return in_array(strtolower((string)$v),['1','true','yes','on'],true);
    };

    $tableExists=static function(string $table) use($db): bool {
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

    $columnMap=static function(string $table) use($db): array {
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

    $findCol=static function(array $map,array $candidates): ?string {
        foreach($candidates as $candidate){
            $key=strtolower($candidate);
            if(isset($map[$key])) return $map[$key];
        }
        return null;
    };

    $binaryToBytes=static function(mixed $value): array {
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

    $guildMarkHtml=static function(mixed $mark,string $name='Guild')
        use($binaryToBytes,$e): string {

        $bytes=$binaryToBytes($mark);

        if(count($bytes)>32){
            $raw='';
            foreach($bytes as $byte) $raw.=chr($byte);

            $hex=preg_replace('/\s+/','',$raw);
            if($hex!==null
                && strlen($hex)>=64
                && preg_match('/^[0-9A-Fa-f]+$/',$hex)){

                $decoded=@hex2bin(substr($hex,0,64));

                if($decoded!==false){
                    $bytes=[];
                    for($i=0;$i<strlen($decoded);$i++){
                        $bytes[]=ord($decoded[$i]);
                    }
                }
            }
        }

        if(count($bytes)<32){
            return '<div class="gpro-mark gpro-mark-empty" title="'.$e($name).'">🏰</div>';
        }

        $palette=[
            '#00000000','#111111','#3b3b3b','#737373',
            '#bdbdbd','#ffffff','#7b1d1d','#d44b3d',
            '#5a360c','#d19c35','#184a1f','#42a84f',
            '#153d66','#3b7fc4','#522267','#a95bd0'
        ];

        $html='<div class="gpro-mark" title="'.$e($name).'">';
        $pixel=0;

        for($i=0;$i<32;$i++){
            $byte=$bytes[$i];

            foreach([($byte>>4)&0x0F,$byte&0x0F] as $nibble){
                if($pixel>=64) break;

                if($nibble===0){
                    $html.='<span class="gpro-px gpro-transparent"></span>';
                }else{
                    $html.='<span class="gpro-px" style="background:'.
                        $palette[$nibble].'"></span>';
                }

                $pixel++;
            }
        }

        return $html.'</div>';
    };

    $styles=static function(): string {
        return <<<'CSS'
<style id="guildRankingProfessionalStyles">
.guild-ranking-professional{
    --gp-panel:#111923;
    --gp-panel2:#16212c;
    --gp-border:#2c3c4a;
    --gp-gold:#d8b15c;
    color:#e8eef2;
}
.guild-ranking-professional *{box-sizing:border-box}
.guild-view-tabs{
    display:flex!important;
    gap:7px!important;
    flex-wrap:wrap;
}
.guild-view-tabs a{
    border:1px solid #394956!important;
    background:#0e161e!important;
    color:#9cacb9!important;
}
.guild-view-tabs a.guild-view-active{
    border-color:#745c31!important;
    background:#211b10!important;
    color:#edcc78!important;
}

.guild-ranking-professional .gpro-header{
    display:flex;
    align-items:flex-end;
    justify-content:space-between;
    gap:16px;
    margin:20px 0 14px;
    padding:17px 18px;
    border:1px solid #3f3728;
    border-radius:11px;
    background:
        radial-gradient(circle at 86% 20%,rgba(216,177,92,.13),transparent 32%),
        linear-gradient(135deg,#17120d,#0c141b 60%,#111b24);
}
.guild-ranking-professional .gpro-header h2{
    color:#f1d486;
    margin:3px 0 4px;
    font-size:24px;
}
.guild-ranking-professional .gpro-header p{
    margin:0;
    color:#8fa2b0;
    font-size:11px;
}
.guild-ranking-professional .gpro-badge{
    flex:0 0 auto;
    padding:6px 9px;
    border:1px solid #5d4d2e;
    border-radius:999px;
    color:#e5c46f;
    background:#19150e;
    font-size:9px;
    font-weight:800;
    letter-spacing:.08em;
}

.guild-ranking-professional .gpro-kpis{
    display:grid;
    grid-template-columns:repeat(4,minmax(130px,1fr));
    gap:9px;
    margin-bottom:14px;
}
.guild-ranking-professional .gpro-kpi{
    background:linear-gradient(180deg,var(--gp-panel2),var(--gp-panel));
    border:1px solid var(--gp-border);
    border-radius:9px;
    padding:12px;
}
.guild-ranking-professional .gpro-kpi span{
    display:block;
    color:#8498a8;
    font-size:9px;
    text-transform:uppercase;
    letter-spacing:.07em;
}
.guild-ranking-professional .gpro-kpi strong{
    display:block;
    margin-top:3px;
    color:#f0d898;
    font-size:22px;
}

.guild-ranking-professional .gpro-toolbar{
    display:flex;
    flex-wrap:wrap;
    align-items:end;
    gap:9px;
    padding:12px;
    margin-bottom:14px;
    background:#101821;
    border:1px solid var(--gp-border);
    border-radius:9px;
}
.guild-ranking-professional .gpro-toolbar label{
    display:grid;
    gap:4px;
    color:#91a4b2;
    font-size:10px;
}
.guild-ranking-professional input,
.guild-ranking-professional select{
    min-height:36px;
    padding:7px 9px;
    border:1px solid #344a5a;
    border-radius:6px;
    background:#081019;
    color:#eaf0f3;
}
.guild-ranking-professional .gpro-btn{
    min-height:36px;
    display:inline-flex;
    align-items:center;
    justify-content:center;
    padding:7px 12px;
    border:1px solid #66542f;
    border-radius:6px;
    background:#2b2519;
    color:#f0d47d;
    font-weight:700;
    text-decoration:none;
    cursor:pointer;
}
.guild-ranking-professional .gpro-info{
    margin-left:auto;
    align-self:center;
    color:#6f8292;
    font-size:9px;
}

.guild-ranking-professional .gpro-grid{
    display:grid;
    grid-template-columns:repeat(2,minmax(330px,1fr));
    gap:12px;
}
.guild-ranking-professional .gpro-card{
    position:relative;
    overflow:hidden;
    padding:14px;
    border:1px solid var(--gp-border);
    border-radius:11px;
    background:linear-gradient(145deg,#151f29,#0d151d);
}
.guild-ranking-professional .gpro-card:after{
    content:"";
    position:absolute;
    width:140px;
    height:140px;
    right:-50px;
    bottom:-55px;
    border-radius:50%;
    background:radial-gradient(circle,rgba(216,177,92,.07),transparent 70%);
    pointer-events:none;
}
.guild-ranking-professional .gpro-card-head{
    display:grid;
    grid-template-columns:66px minmax(0,1fr) auto;
    gap:11px;
    align-items:center;
    padding-bottom:10px;
    border-bottom:1px solid #263541;
}
.guild-ranking-professional .gpro-mark{
    width:58px;
    height:58px;
    display:grid;
    grid-template-columns:repeat(8,1fr);
    grid-template-rows:repeat(8,1fr);
    padding:4px;
    border:2px solid #8c6c31;
    border-radius:7px;
    background:#070b0f;
    image-rendering:pixelated;
}
.guild-ranking-professional .gpro-mark-empty{
    display:flex;
    align-items:center;
    justify-content:center;
    color:#887247;
    font-size:27px;
}
.guild-ranking-professional .gpro-px{display:block}
.guild-ranking-professional .gpro-transparent{background:transparent!important}
.guild-ranking-professional .gpro-name{
    color:#f3d780;
    font-size:19px;
    font-weight:900;
}
.guild-ranking-professional .gpro-master{
    margin-top:3px;
    color:#98aab7;
    font-size:10px;
}
.guild-ranking-professional .gpro-master a{
    color:#c9d4dc;
    text-decoration:none;
}
.guild-ranking-professional .gpro-master a:hover{color:#f0cf79}
.guild-ranking-professional .gpro-score{text-align:right}
.guild-ranking-professional .gpro-score span{
    display:block;
    color:#748898;
    font-size:8px;
    text-transform:uppercase;
}
.guild-ranking-professional .gpro-score strong{
    color:#d8b15c;
    font-size:17px;
}

.guild-ranking-professional .gpro-stats{
    display:grid;
    grid-template-columns:repeat(3,1fr);
    gap:7px;
    margin:10px 0;
}
.guild-ranking-professional .gpro-stat{
    padding:8px;
    border:1px solid #1f2d38;
    border-radius:6px;
    background:#0a1219;
}
.guild-ranking-professional .gpro-stat span{
    display:block;
    color:#7f93a2;
    font-size:8px;
    text-transform:uppercase;
}
.guild-ranking-professional .gpro-stat strong{
    display:block;
    margin-top:2px;
    color:#ecf1f4;
    font-size:15px;
}
.guild-ranking-professional .gpro-notice{
    min-height:39px;
    padding:9px 10px;
    border-left:3px solid #a48545;
    border-radius:5px;
    background:#111820;
    color:#cbd6dd;
    font-size:10px;
    font-style:italic;
}
.guild-ranking-professional .gpro-members{
    display:flex;
    flex-wrap:wrap;
    gap:5px;
    margin-top:9px;
}
.guild-ranking-professional .gpro-member{
    padding:4px 7px;
    border:1px solid #253541;
    border-radius:999px;
    background:#0a1219;
    color:#9eb0bc;
    font-size:9px;
}
.guild-ranking-professional .gpro-empty{
    padding:28px;
    text-align:center;
    border:1px dashed #344956;
    border-radius:9px;
    background:#0d151d;
    color:#90a2af;
}
.guild-ranking-professional .gpro-footnote{
    display:flex;
    align-items:flex-start;
    gap:8px;
    margin-top:14px;
    padding:10px 12px;
    border:1px solid #493e29;
    border-radius:7px;
    background:rgba(125,95,38,.08);
    color:#9d9276;
    font-size:9px;
}

@media(max-width:1000px){
    .guild-ranking-professional .gpro-grid{grid-template-columns:1fr}
    .guild-ranking-professional .gpro-kpis{grid-template-columns:1fr 1fr}
}
@media(max-width:620px){
    .guild-ranking-professional .gpro-header{
        align-items:flex-start;
        flex-direction:column;
    }
    .guild-ranking-professional .gpro-card-head{
        grid-template-columns:58px 1fr;
    }
    .guild-ranking-professional .gpro-score{
        grid-column:1/-1;
        text-align:left;
    }
    .guild-ranking-professional .gpro-stats{grid-template-columns:1fr 1fr}
    .guild-ranking-professional .gpro-kpis{grid-template-columns:1fr 1fr}
    .guild-ranking-professional .gpro-info{
        width:100%;
        margin-left:0;
    }
}
</style>
CSS;
    };

    // ------------------------------------------------------------
    // Admin diagnostics only
    // ------------------------------------------------------------
    if($hook==='admin.panel'){
        $guildOk=$tableExists('Guild');
        $memberOk=$tableExists('GuildMember');
        $charOk=$tableExists('Character');

        $guildCount=0;
        if($guildOk){
            $row=$db->fetchOne("SELECT COUNT(*) AS total FROM dbo.Guild");
            $guildCount=(int)($row['total']??0);
        }

        return $styles().'
        <div class="guild-ranking-professional">
            <div class="gpro-header">
                <div>
                    <span class="eyebrow">RANKINGS OVERRIDE</span>
                    <h2>🏰 Guild Ranking Professional</h2>
                    <p>Agrega la vista Profesional dentro del ranking público de Guilds.</p>
                </div>
                <span class="gpro-badge">PUBLIC OVERRIDE</span>
            </div>

            <div class="gpro-kpis">
                <div class="gpro-kpi"><span>Guild</span><strong>'.
                    ($guildOk?'ONLINE':'MISSING').'</strong></div>
                <div class="gpro-kpi"><span>GuildMember</span><strong>'.
                    ($memberOk?'ONLINE':'MISSING').'</strong></div>
                <div class="gpro-kpi"><span>Character</span><strong>'.
                    ($charOk?'ONLINE':'MISSING').'</strong></div>
                <div class="gpro-kpi"><span>Clanes</span><strong>'.
                    number_format($guildCount,0,',','.').'</strong></div>
            </div>

            <div class="gpro-footnote">
                <span>✓</span>
                <div>
                    <strong>Integración automática:</strong>
                    al estar activo agrega la pestaña <strong>✨ Profesional</strong> dentro de <code>/rankings?type=guilds</code>.
                    Al desactivarlo desaparece únicamente la vista Profesional.
                    No agrega accesos a Mi Cuenta.
                </div>
            </div>
        </div>';
    }

    // ------------------------------------------------------------
    // Only override public rankings page, Guilds tab.
    // ------------------------------------------------------------
    if($hook!=='site.head'){
        return null;
    }

    if((string)($payload['template']??'')!=='rankings'){
        return null;
    }

    $type=strtolower(trim((string)($_GET['type']??'level')));
    if($type!=='guilds'){
        return null;
    }

    $view=strtolower(trim((string)($_GET['view']??'basic')));

    /*
     * La vista profesional solo reemplaza el contenido cuando fue
     * seleccionada explícitamente desde Rankings.
     */
    if($view!=='professional'){
        return '<script>
        (function(){
            function addProfessionalTab(){
                const groups=document.querySelector(".ranking-groups");
                if(!groups) return;

                let tabs=groups.querySelector("[data-guild-view-tabs]");
                if(!tabs){
                    tabs=document.createElement("div");
                    tabs.setAttribute("data-guild-view-tabs","1");
                    tabs.className="ranking-tabs guild-view-tabs";
                    tabs.style.marginTop="10px";
                    groups.appendChild(tabs);
                }

                if(!tabs.querySelector("[data-guild-professional]")){
                    const a=document.createElement("a");
                    a.href="/rankings?type=guilds&view=professional";
                    a.textContent="✨ Profesional";
                    a.setAttribute("data-guild-professional","1");
                    tabs.appendChild(a);
                }
            }

            if(document.readyState==="loading"){
                document.addEventListener("DOMContentLoaded",addProfessionalTab,{once:true});
            }else{
                addProfessionalTab();
            }
        })();
        </script>';
    }

    if(!$tableExists('Guild')
        || !$tableExists('GuildMember')
        || !$tableExists('Character')){
        return null; // classic remains visible
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
        return null; // fail-safe: retain classic ranking
    }

    $search=trim((string)($_GET['q']??''));
    $sort=trim((string)($_GET['sort']??($config['default_sort']??'resets')));

    if(!in_array($sort,['resets','members','score','name'],true)){
        $sort='resets';
    }

    $limit=max(10,min(200,(int)($config['page_limit']??100)));

    $params=[];
    $where='1=1';

    if($search!==''){
        $needle='%'.$search.'%';
        $parts=[];

        $parts[]="g.[{$gName}] LIKE :q_name";
        $params['q_name']=$needle;

        if($gMaster){
            $parts[]="g.[{$gMaster}] LIKE :q_master";
            $params['q_master']=$needle;
        }

        if($gNotice){
            $parts[]="g.[{$gNotice}] LIKE :q_notice";
            $params['q_notice']=$needle;
        }

        $where.=' AND ('.implode(' OR ',$parts).')';
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
            ISNULL(a.MemberCount,0) AS MemberCount,
            ISNULL(a.TotalResets,0) AS TotalResets
        FROM dbo.Guild g
        LEFT JOIN (
            SELECT
                gm.[{$mGuild}] AS GuildName,
                COUNT(gm.[{$mName}]) AS MemberCount,
                ISNULL(SUM(ISNULL(c.[{$cReset}],0)),0) AS TotalResets
            FROM dbo.GuildMember gm
            LEFT JOIN dbo.Character c
                ON c.[{$cName}] = gm.[{$mName}]
            GROUP BY gm.[{$mGuild}]
        ) a
            ON a.GuildName = g.[{$gName}]
        WHERE {$where}
        ORDER BY {$order}
    ";

    $guilds=$db->fetchAll($sql,$params);

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

    $memberPreview=[];

    if($cfgBool('show_member_preview',true)){
        foreach($guilds as $guild){
            $guildName=(string)($guild['GuildName']??'');
            if($guildName==='') continue;

            $memberPreview[$guildName]=$db->fetchAll("
                SELECT TOP 6
                    gm.[{$mName}] AS Name,
                    ISNULL(c.[{$cReset}],0) AS ResetCount
                FROM dbo.GuildMember gm
                LEFT JOIN dbo.Character c
                    ON c.[{$cName}] = gm.[{$mName}]
                WHERE gm.[{$mGuild}]=:guild
                ORDER BY ISNULL(c.[{$cReset}],0) DESC,
                         gm.[{$mName}] ASC
            ",['guild'=>$guildName]);
        }
    }

    // ------------------------------------------------------------
    // Build professional content server-side.
    // ------------------------------------------------------------
    $html='<div class="guild-ranking-professional" id="guildProfessionalRanking">';

    $html.='<div class="gpro-header">
        <div>
            <span class="eyebrow">GUILD DIRECTORY</span>
            <h2>🏰 Clanes del Servidor</h2>
            <p>Ranking profesional integrado directamente dentro de Rankings.</p>
        </div>
        <span class="gpro-badge">PROFESSIONAL</span>
    </div>';

    $html.='<div class="gpro-kpis">
        <div class="gpro-kpi"><span>Clanes</span><strong>'.
            number_format((int)($stats['GuildCount']??0),0,',','.').'</strong></div>
        <div class="gpro-kpi"><span>Miembros</span><strong>'.
            number_format((int)($stats['MemberCount']??0),0,',','.').'</strong></div>
        <div class="gpro-kpi"><span>Resets acumulados</span><strong>'.
            number_format((int)($stats['TotalResets']??0),0,',','.').'</strong></div>
        <div class="gpro-kpi"><span>Top Guild Resets</span><strong>'.
            number_format($topReset,0,',','.').'</strong></div>
    </div>';

    $html.='<form method="get" action="/rankings" class="gpro-toolbar">
        <input type="hidden" name="type" value="guilds">

        <label>Buscar clan / master / notice
            <input name="q" value="'.$e($search).'"
                   placeholder="Nombre del clan...">
        </label>

        <label>Ordenar por
            <select name="sort">
                <option value="resets" '.($sort==='resets'?'selected':'').'>
                    Total Resets
                </option>
                <option value="members" '.($sort==='members'?'selected':'').'>
                    Miembros
                </option>
                <option value="score" '.($sort==='score'?'selected':'').'>
                    Guild Score
                </option>
                <option value="name" '.($sort==='name'?'selected':'').'>
                    Nombre
                </option>
            </select>
        </label>

        <button class="gpro-btn" type="submit">Aplicar</button>';

    if($search!=='' || $sort!==($config['default_sort']??'resets')){
        $html.='<a class="gpro-btn" href="/rankings?type=guilds">Limpiar</a>';
    }

    $html.='<span class="gpro-info">Hasta '.$limit.' clanes · datos en tiempo real</span>
    </form>';

    if(!$guilds){
        $html.='<div class="gpro-empty">
            <h3>🏰 No se encontraron clanes</h3>
            <p>No existen coincidencias para el filtro aplicado.</p>
        </div>';
    }else{
        $html.='<div class="gpro-grid">';

        foreach($guilds as $guild){
            $name=(string)($guild['GuildName']??'Sin nombre');
            $master=trim((string)($guild['GuildMaster']??''));
            $notice=trim((string)($guild['GuildNotice']??''));
            $score=(int)($guild['GuildScore']??0);
            $members=(int)($guild['MemberCount']??0);
            $resets=(int)($guild['TotalResets']??0);

            $html.='<article class="gpro-card">
                <div class="gpro-card-head">'.
                    $guildMarkHtml($guild['GuildMark']??null,$name).'
                    <div>
                        <div class="gpro-name">'.$e($name).'</div>
                        <div class="gpro-master">
                            👑 Guild Master: ';

            if($master!==''){
                $html.='<a href="/character/'.rawurlencode($master).'"><strong>'.
                    $e($master).'</strong></a>';
            }else{
                $html.='<strong>—</strong>';
            }

            $html.='</div>
                    </div>
                    <div class="gpro-score">';

            if($cfgBool('show_score',true)){
                $html.='<span>Guild Score</span><strong>'.
                    number_format($score,0,',','.').'</strong>';
            }

            $html.='</div>
                </div>

                <div class="gpro-stats">
                    <div class="gpro-stat">
                        <span>Miembros</span>
                        <strong>👥 '.number_format($members,0,',','.').'</strong>
                    </div>
                    <div class="gpro-stat">
                        <span>Total Resets</span>
                        <strong>♻ '.number_format($resets,0,',','.').'</strong>
                    </div>
                    <div class="gpro-stat">
                        <span>Promedio</span>
                        <strong>'.
                            ($members>0?number_format($resets/$members,1,',','.'):'0').
                        '</strong>
                    </div>
                </div>';

            if($cfgBool('show_notice',true)){
                $html.='<div class="gpro-notice">
                    📜 '.($notice!==''?$e($notice):'Este clan no tiene notice.').'
                </div>';
            }

            if($cfgBool('show_member_preview',true)
                && !empty($memberPreview[$name])){

                $html.='<div class="gpro-members">';

                foreach($memberPreview[$name] as $member){
                    $memberName=(string)$member['Name'];

                    $html.='<a class="gpro-member"
                              href="/character/'.rawurlencode($memberName).'">
                        ⚔ '.$e($memberName).' · R '.
                        number_format((int)$member['ResetCount'],0,',','.').
                    '</a>';
                }

                $html.='</div>';
            }

            $html.='</article>';
        }

        $html.='</div>';
    }

    $html.='<div class="gpro-footnote">
        <span>Ω</span>
        <div>
            <strong>Total Resets</strong> se calcula en tiempo real desde
            <code>Character.ResetCount</code> mediante
            <code>GuildMember</code>. El módulo es completamente público y
            READ ONLY.
        </div>
    </div>';

    $html.='</div>';

    /*
     * El HTML se genera en PHP. JavaScript solamente reemplaza el bloque
     * visual clásico ya renderizado por rankings.php.
     *
     * Si JS estuviera desactivado, el ranking clásico continúa disponible.
     */
    $encoded=json_encode(
        $html,
        JSON_UNESCAPED_UNICODE|
        JSON_UNESCAPED_SLASHES|
        JSON_HEX_TAG|
        JSON_HEX_AMP|
        JSON_HEX_APOS|
        JSON_HEX_QUOT
    );

    return $styles().'
    <script>
    (function(){
        const professionalHtml='.$encoded.';

        function applyGuildProfessionalRanking(){
            const section=document.querySelector(".rankings-section .container");
            if(!section) return;

            const groups=section.querySelector(".ranking-groups");
            if(!groups) return;

            let tabs=groups.querySelector("[data-guild-view-tabs]");
            if(!tabs){
                tabs=document.createElement("div");
                tabs.setAttribute("data-guild-view-tabs","1");
                tabs.className="ranking-tabs guild-view-tabs";
                tabs.style.marginTop="10px";
                groups.appendChild(tabs);
            }

            if(!tabs.querySelector("[data-guild-basic]")){
                const basic=document.createElement("a");
                basic.href="/rankings?type=guilds&view=basic";
                basic.textContent="🏰 Básico";
                basic.setAttribute("data-guild-basic","1");
                tabs.appendChild(basic);
            }

            if(!tabs.querySelector("[data-guild-professional]")){
                const professional=document.createElement("a");
                professional.href="/rankings?type=guilds&view=professional";
                professional.textContent="✨ Profesional";
                professional.setAttribute("data-guild-professional","1");
                tabs.appendChild(professional);
            }

            const active=tabs.querySelector("[data-guild-professional]");
            if(active) active.classList.add("guild-view-active");

            let node=groups.nextElementSibling;

            while(node){
                const next=node.nextElementSibling;
                node.remove();
                node=next;
            }

            const holder=document.createElement("div");
            holder.innerHTML=professionalHtml;

            while(holder.firstChild){
                section.appendChild(holder.firstChild);
            }

            const hero=document.querySelector(".rankings-hero p");
            if(hero){
                hero.textContent=
                    "Explora los clanes, Guild Masters, miembros y resets acumulados.";
            }

            document.documentElement.setAttribute(
                "data-guild-ranking",
                "professional"
            );
        }

        if(document.readyState==="loading"){
            document.addEventListener(
                "DOMContentLoaded",
                applyGuildProfessionalRanking,
                {once:true}
            );
        }else{
            applyGuildProfessionalRanking();
        }
    })();
    </script>';
};
