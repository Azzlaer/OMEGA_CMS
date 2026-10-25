<?php
declare(strict_types=1);

/**
 * Omega MU CMS
 * Guild Ranking Basic v1.0.0
 *
 * Público, READ ONLY y sin user_menu.
 *
 * URL:
 * /rankings?type=guilds
 * /rankings?type=guilds&view=basic
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

    if($hook==='admin.panel'){
        return '
        <div style="padding:16px;border:1px solid #31404d;border-radius:10px;background:#101821">
            <span style="font-size:10px;color:#8fa0af;letter-spacing:.1em">RANKINGS MODULE</span>
            <h2 style="margin:5px 0;color:#e6c574">🏰 Guild Ranking Basic</h2>
            <p style="color:#8999a7">
                Ranking público básico de clanes. Se integra directamente en
                <code>/rankings?type=guilds</code>.
            </p>
            <p style="color:#6ed29a">✓ READ ONLY · ✓ Público · ✓ Sin Mi Cuenta</p>
        </div>';
    }

    if($hook!=='site.head'){
        return null;
    }

    if((string)($payload['template']??'')!=='rankings'){
        return null;
    }

    if(strtolower((string)($_GET['type']??'level'))!=='guilds'){
        return null;
    }

    $view=strtolower(trim((string)($_GET['view']??'basic')));

    // Si el visitante pidió profesional, este módulo no reemplaza contenido.
    if($view==='professional'){
        // Solo agregamos el tab Básico; el profesional hará lo suyo.
        return '<script>
        (function(){
            function addBasicTab(){
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

                if(!tabs.querySelector("[data-guild-basic]")){
                    const a=document.createElement("a");
                    a.href="/rankings?type=guilds&view=basic";
                    a.textContent="🏰 Básico";
                    a.setAttribute("data-guild-basic","1");
                    tabs.appendChild(a);
                }
            }

            if(document.readyState==="loading"){
                document.addEventListener("DOMContentLoaded",addBasicTab,{once:true});
            }else{
                addBasicTab();
            }
        })();
        </script>';
    }

    $limit=max(10,min(200,(int)($config['page_limit']??100)));

    $columns=$db->fetchAll("
        SELECT TABLE_NAME,COLUMN_NAME
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA='dbo'
          AND TABLE_NAME IN ('Guild','GuildMember')
    ");

    $map=[];
    foreach($columns as $row){
        $map[strtolower((string)$row['TABLE_NAME'])][strtolower((string)$row['COLUMN_NAME'])]
            =(string)$row['COLUMN_NAME'];
    }

    $g=$map['guild']??[];
    $gm=$map['guildmember']??[];

    $gName=$g['g_name']??null;
    $gMaster=$g['g_master']??null;
    $gScore=$g['g_score']??null;
    $mGuild=$gm['g_name']??null;
    $mName=$gm['name']??null;

    if(!$gName || !$mGuild || !$mName){
        return null;
    }

    $masterSelect=$gMaster ? "g.[{$gMaster}]" : "''";
    $scoreSelect=$gScore ? "ISNULL(g.[{$gScore}],0)" : "0";

    $rows=$db->fetchAll("
        SELECT TOP {$limit}
            g.[{$gName}] AS GuildName,
            {$masterSelect} AS GuildMaster,
            {$scoreSelect} AS GuildScore,
            ISNULL(m.MemberCount,0) AS MemberCount
        FROM dbo.Guild g
        LEFT JOIN (
            SELECT
                [{$mGuild}] AS GuildName,
                COUNT([{$mName}]) AS MemberCount
            FROM dbo.GuildMember
            GROUP BY [{$mGuild}]
        ) m
            ON m.GuildName=g.[{$gName}]
        ORDER BY
            ISNULL(m.MemberCount,0) DESC,
            {$scoreSelect} DESC,
            g.[{$gName}] ASC
    ");

    $html='<div class="guild-basic-ranking" id="guildBasicRanking">
        <div class="gbr-head">
            <div>
                <span>GUILD RANKING</span>
                <h2>🏰 Ranking Básico de Clanes</h2>
                <p>Vista simple integrada en Rankings.</p>
            </div>
            <strong>BASIC</strong>
        </div>

        <div class="gbr-table-wrap">
        <table class="gbr-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Clan</th>';

    if($cfgBool('show_master',true)){
        $html.='<th>Guild Master</th>';
    }

    $html.='<th>Miembros</th>';

    if($cfgBool('show_score',true)){
        $html.='<th>Score</th>';
    }

    $html.='</tr></thead><tbody>';

    $pos=0;
    foreach($rows as $row){
        $pos++;
        $html.='<tr>
            <td><span class="gbr-rank">#'.$pos.'</span></td>
            <td><strong>'.$e((string)$row['GuildName']).'</strong></td>';

        if($cfgBool('show_master',true)){
            $master=trim((string)($row['GuildMaster']??''));
            $html.='<td>'.($master!==''?
                '<a href="/character/'.rawurlencode($master).'">'.$e($master).'</a>'
                :'—').'</td>';
        }

        $html.='<td>'.number_format((int)$row['MemberCount'],0,',','.').'</td>';

        if($cfgBool('show_score',true)){
            $html.='<td>'.number_format((int)$row['GuildScore'],0,',','.').'</td>';
        }

        $html.='</tr>';
    }

    if(!$rows){
        $colspan=3+($cfgBool('show_master',true)?1:0)+($cfgBool('show_score',true)?1:0);
        $html.='<tr><td colspan="'.$colspan.'">No hay clanes registrados.</td></tr>';
    }

    $html.='</tbody></table></div></div>';

    $encoded=json_encode(
        $html,
        JSON_UNESCAPED_UNICODE|
        JSON_UNESCAPED_SLASHES|
        JSON_HEX_TAG|
        JSON_HEX_AMP|
        JSON_HEX_APOS|
        JSON_HEX_QUOT
    );

    return <<<'STYLE'
<style>
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
.guild-basic-ranking{margin-top:16px;color:#e9eef2}
.guild-basic-ranking .gbr-head{
    display:flex;justify-content:space-between;gap:15px;align-items:center;
    border:1px solid #3b4145;border-top-color:#775c2d;border-radius:10px;
    background:linear-gradient(135deg,#16120d,#101820);
    padding:16px 18px;margin-bottom:12px
}
.guild-basic-ranking .gbr-head span{
    color:#8394a4;font-size:9px;letter-spacing:.13em
}
.guild-basic-ranking .gbr-head h2{
    margin:3px 0;color:#edcd7a;font-size:22px
}
.guild-basic-ranking .gbr-head p{margin:0;color:#8295a4;font-size:10px}
.guild-basic-ranking .gbr-head>strong{
    color:#d5b45f;border:1px solid #5d4b29;border-radius:999px;
    padding:5px 8px;font-size:9px
}
.guild-basic-ranking .gbr-table-wrap{
    overflow-x:auto;border:1px solid #293844;border-radius:9px
}
.guild-basic-ranking .gbr-table{
    width:100%;border-collapse:collapse;min-width:620px
}
.guild-basic-ranking th{
    text-align:left;background:#091119;color:#748696;
    font-size:9px;text-transform:uppercase;padding:10px 12px
}
.guild-basic-ranking td{
    background:#111922;border-top:1px solid #22313c;
    padding:11px 12px;font-size:11px
}
.guild-basic-ranking tr:hover td{background:#141e28}
.guild-basic-ranking td strong{color:#edcd7a}
.guild-basic-ranking td a{color:#b9c7d1}
.guild-basic-ranking .gbr-rank{
    color:#cba751;font-weight:800
}
</style>
STYLE
    .'<script>
    (function(){
        const html='.$encoded.';

        function renderBasic(){
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
                const a=document.createElement("a");
                a.href="/rankings?type=guilds&view=basic";
                a.textContent="🏰 Básico";
                a.setAttribute("data-guild-basic","1");
                tabs.appendChild(a);
            }

            const basic=tabs.querySelector("[data-guild-basic]");
            if(basic) basic.classList.add("guild-view-active");

            let node=groups.nextElementSibling;
            while(node){
                const next=node.nextElementSibling;
                node.remove();
                node=next;
            }

            const holder=document.createElement("div");
            holder.innerHTML=html;

            while(holder.firstChild){
                section.appendChild(holder.firstChild);
            }
        }

        if(document.readyState==="loading"){
            document.addEventListener("DOMContentLoaded",renderBasic,{once:true});
        }else{
            renderBasic();
        }
    })();
    </script>';
};
