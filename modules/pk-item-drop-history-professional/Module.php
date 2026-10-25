<?php
declare(strict_types=1);

/**
 * Omega MU CMS
 * PK Item Drop History Professional v1.0.0
 *
 * READ ONLY:
 * - PKItemDropHistory
 * - Character
 *
 * El módulo detecta columnas en runtime porque distintas builds
 * pueden usar nombres ligeramente diferentes.
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

    $limit=max(10,min(200,(int)($config['history_limit']??50)));

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

    $read = static function(array $row,array $map,array $candidates) use($findCol): mixed {
        $col=$findCol($map,$candidates);
        return $col!==null ? ($row[$col]??null) : null;
    };

    $toHex = static function(mixed $value): string {
        if($value===null) return '';
        if(is_resource($value)){
            $data=stream_get_contents($value);
            return $data===false?'':strtoupper(bin2hex($data));
        }
        if(is_string($value)){
            // If already looks hexadecimal, preserve it.
            $clean=preg_replace('/\s+/','',$value);
            if($clean!==null && $clean!=='' && preg_match('/^[0-9A-Fa-f]+$/',$clean)){
                return strtoupper($clean);
            }
            return strtoupper(bin2hex($value));
        }
        return (string)$value;
    };

    $mapName = static function(mixed $map): string {
        if($map===null || $map==='') return '—';
        $n=(int)$map;
        $names=[
            0=>'Lorencia',1=>'Dungeon',2=>'Devias',3=>'Noria',4=>'Lost Tower',
            6=>'Arena',7=>'Atlans',8=>'Tarkan',9=>'Devil Square',10=>'Icarus',
            11=>'Blood Castle I',12=>'Blood Castle II',13=>'Blood Castle III',
            14=>'Blood Castle IV',15=>'Blood Castle V',16=>'Blood Castle VI'
        ];
        return $names[$n]??('Map '.$n);
    };

    $renderStyles = static function(): string {
        return <<<'CSS'
<style>
.pkdrop{--pk-bg:#0b1016;--pk-panel:#111922;--pk-panel2:#17212b;--pk-border:#2c3946;--pk-gold:#d1aa54;--pk-red:#e06b64;--pk-green:#72d49e;color:#e7edf2}
.pkdrop *{box-sizing:border-box}
.pkdrop .pk-hero{position:relative;overflow:hidden;border:1px solid #49302e;border-radius:14px;padding:24px;background:radial-gradient(circle at 82% 20%,rgba(205,65,57,.22),transparent 35%),linear-gradient(135deg,#21100f,#0c1218 54%,#111a22);margin-bottom:18px}
.pkdrop .pk-hero:after{content:"☠";position:absolute;right:28px;top:-20px;font-size:150px;color:rgba(224,107,100,.07)}
.pkdrop .pk-eyebrow{font-size:11px;letter-spacing:.15em;font-weight:800;color:#9caab7}
.pkdrop h2,.pkdrop h3{color:#f1d37d;margin:5px 0}
.pkdrop .pk-hero h2{font-size:30px}
.pkdrop .pk-muted{color:#8fa1af}
.pkdrop .pk-kpis{display:grid;grid-template-columns:repeat(5,minmax(120px,1fr));gap:10px;margin:16px 0}
.pkdrop .pk-kpi{background:linear-gradient(180deg,var(--pk-panel2),var(--pk-panel));border:1px solid var(--pk-border);border-radius:10px;padding:14px}
.pkdrop .pk-kpi span{display:block;color:#8fa1af;font-size:11px;text-transform:uppercase;letter-spacing:.07em}
.pkdrop .pk-kpi strong{display:block;font-size:25px;margin-top:4px;color:#f3dfaa}
.pkdrop .red{color:#ff8e86!important}.pkdrop .green{color:#79dfa8!important}.pkdrop .gold{color:#e7c36d!important}
.pkdrop .pk-toolbar{display:flex;gap:10px;align-items:end;flex-wrap:wrap;background:var(--pk-panel);border:1px solid var(--pk-border);border-radius:10px;padding:13px;margin:14px 0}
.pkdrop .pk-toolbar label{display:grid;gap:5px;color:#9fb1bf;font-size:12px}
.pkdrop select{min-width:190px;background:#081019;color:#edf2f5;border:1px solid #344858;border-radius:6px;padding:8px}
.pkdrop .pk-btn{display:inline-flex;align-items:center;justify-content:center;text-decoration:none;border:1px solid #66542f;background:#2b2519;color:#f0d47d;border-radius:6px;padding:8px 13px;font-weight:700}
.pkdrop .pk-btn:hover{background:#41351f}
.pkdrop .pk-table-wrap{overflow:auto;border:1px solid var(--pk-border);border-radius:10px;background:#0d151d}
.pkdrop table{width:100%;border-collapse:collapse;min-width:980px}
.pkdrop th{padding:10px 11px;text-align:left;font-size:11px;text-transform:uppercase;letter-spacing:.06em;color:#91a4b3;background:#121c25;border-bottom:1px solid var(--pk-border)}
.pkdrop td{padding:11px;border-bottom:1px solid #1d2a35;vertical-align:middle}
.pkdrop tbody tr:hover{background:rgba(224,107,100,.045)}
.pkdrop .pk-victim{font-weight:900;color:#f0d57e}
.pkdrop .pk-killer{font-weight:900;color:#ff9188}
.pkdrop .pk-item{font-weight:800;color:#e8eef2}
.pkdrop .pk-badge{display:inline-flex;border-radius:999px;padding:4px 8px;font-size:10px;font-weight:900;border:1px solid rgba(224,107,100,.3);background:rgba(224,107,100,.1);color:#ff9188}
.pkdrop .pk-detail{font-size:11px;color:#8fa2b0;margin-top:3px}
.pkdrop .pk-hex{font-family:Consolas,monospace;font-size:10px;max-width:260px;overflow-wrap:anywhere;color:#9eb7c8}
.pkdrop .pk-empty{padding:28px;text-align:center;border:1px dashed #344956;border-radius:10px;background:#0d151d;color:#90a2af}
.pkdrop .pk-note{border:1px solid #57482b;background:rgba(125,95,38,.10);color:#d8c693;border-radius:8px;padding:10px 12px;margin-top:12px}
.pkdrop .pk-section{margin-top:18px}
@media(max-width:980px){.pkdrop .pk-kpis{grid-template-columns:repeat(3,1fr)}}
@media(max-width:650px){.pkdrop .pk-kpis{grid-template-columns:1fr 1fr}}
</style>
CSS;
    };

    if($hook==='admin.panel'){
        $exists=$tableExists('PKItemDropHistory');
        $cols=$exists?$columnMap('PKItemDropHistory'):[];
        $count=0;
        if($exists){
            $r=$db->fetchOne("SELECT COUNT(*) AS total FROM dbo.PKItemDropHistory");
            $count=(int)($r['total']??0);
        }

        $expected=[
            ['VictimName','Victim','CharacterName'],
            ['KillerName','Killer'],
            ['VictimAccount','VictimAccountID','AccountID'],
            ['KillerAccount','KillerAccountID'],
            ['ItemIndex','ItemID','ItemCode'],
            ['ItemLevel','Level'],
            ['ItemExcellent','Excellent','ExcellentOption'],
            ['ItemLuck','Luck'],
            ['ItemSkill','Skill'],
            ['ItemOption','Option','AdditionalOption'],
            ['MapNumber','Map'],
            ['MapX','PosX','X'],
            ['MapY','PosY','Y'],
            ['ItemHex','ItemData','ItemBytes'],
            ['DropDate','CreatedAt','Date','EventDate']
        ];

        $rows='';
        foreach($expected as $candidates){
            $found=$findCol($cols,$candidates);
            $rows.='<tr><td><code>'.$e(implode(' / ',$candidates)).'</code></td><td style="color:'.
                ($found?'#72d49e':'#ff8e86').'">'.($found?'✓ '.$e($found):'○ No detectada').'</td></tr>';
        }

        return $renderStyles().'
        <div class="pkdrop">
          <div class="pk-hero">
            <span class="pk-eyebrow">MODULE DIAGNOSTICS</span>
            <h2>☠️ PK Item Drop History Professional</h2>
            <p class="pk-muted">Diagnóstico READ ONLY de PKItemDropHistory.</p>
          </div>

          <div class="pk-kpis">
            <div class="pk-kpi"><span>Tabla</span><strong class="'.($exists?'green':'red').'">'.($exists?'ONLINE':'MISSING').'</strong></div>
            <div class="pk-kpi"><span>Registros</span><strong>'.number_format($count,0,',','.').'</strong></div>
            <div class="pk-kpi"><span>Columnas</span><strong>'.count($cols).'</strong></div>
            <div class="pk-kpi"><span>Privacidad</span><strong class="gold">SAFE</strong></div>
            <div class="pk-kpi"><span>Modo</span><strong class="green">READ ONLY</strong></div>
          </div>

          <div class="pk-section">
            <h3>Detección de columnas</h3>
            <div class="pk-table-wrap">
              <table><thead><tr><th>Campo esperado</th><th>Resultado</th></tr></thead><tbody>'.$rows.'</tbody></table>
            </div>
          </div>

          <div class="pk-note">
            🔒 Este módulo no modifica items, inventarios, Character ni PKItemDropHistory.
            Solo consulta datos pertenecientes a la cuenta autenticada.
          </div>
        </div>';
    }

    if($hook!=='user.page'){
        return null;
    }

    $accountId=trim((string)($payload['account_id']??''));
    if($accountId===''){
        throw new RuntimeException('No se recibió una cuenta autenticada.');
    }

    if(!$tableExists('PKItemDropHistory')){
        return [
            'title'=>'PK Drop History',
            'html'=>$renderStyles().'
            <div class="pkdrop">
                <div class="pk-empty">
                    <h3>☠️ PKItemDropHistory no está instalado</h3>
                    <p>La tabla <code>PKItemDropHistory</code> no existe en la base actual.</p>
                </div>
            </div>'
        ];
    }

    $cols=$columnMap('PKItemDropHistory');

    $victimName=$findCol($cols,['VictimName','Victim','CharacterName']);
    $killerName=$findCol($cols,['KillerName','Killer']);
    $victimAccount=$findCol($cols,['VictimAccount','VictimAccountID','VictimAccountId','AccountID']);
    $killerAccount=$findCol($cols,['KillerAccount','KillerAccountID','KillerAccountId']);
    $dateCol=$findCol($cols,['DropDate','CreatedAt','Date','EventDate','CreatedDate','LogDate']);

    if(!$victimName){
        throw new RuntimeException('PKItemDropHistory no contiene una columna de personaje víctima reconocible.');
    }

    $chars=$db->fetchAll(
        "SELECT Name FROM Character WHERE AccountID=:account ORDER BY Name",
        ['account'=>$accountId]
    );
    $owned=array_values(array_filter(array_map(
        static fn(array $r): string => trim((string)($r['Name']??'')),
        $chars
    )));

    $query=(array)($payload['query']??[]);
    $filter=trim((string)($query['character']??''));
    if($filter!=='' && !in_array($filter,$owned,true)){
        $filter='';
    }

    $params=[];
    if($filter!==''){
        $where="[{$victimName}]=:char";
        $params['char']=$filter;
    }elseif($victimAccount){
        $where="[{$victimAccount}]=:account";
        $params['account']=$accountId;
    }elseif($owned){
        $bind=[];
        foreach($owned as $i=>$name){
            $key='n'.$i;
            $params[$key]=$name;
            $bind[]=':'.$key;
        }
        $where="[{$victimName}] IN (".implode(',',$bind).")";
    }else{
        $where='1=0';
    }

    $order=$dateCol?"[{$dateCol}] DESC":"[{$victimName}] ASC";
    $history=$db->fetchAll(
        "SELECT TOP {$limit} * FROM dbo.PKItemDropHistory WHERE {$where} ORDER BY {$order}",
        $params
    );

    $totalRow=$db->fetchOne(
        "SELECT COUNT(*) AS total FROM dbo.PKItemDropHistory WHERE {$where}",
        $params
    );
    $total=(int)($totalRow['total']??0);

    // Aggregate quick stats safely in PHP using the filtered recent rows.
    $uniqueKillers=[];
    $uniqueMaps=[];
    $itemsWithHex=0;
    foreach($history as $row){
        $killer=(string)($read($row,$cols,['KillerName','Killer'])??'');
        if($killer!=='') $uniqueKillers[strtolower($killer)]=$killer;

        $map=$read($row,$cols,['MapNumber','Map']);
        if($map!==null && $map!=='') $uniqueMaps[(string)$map]=true;

        $hex=$read($row,$cols,['ItemHex','ItemData','ItemBytes']);
        if($hex!==null && $hex!=='') $itemsWithHex++;
    }

    $html=$renderStyles();
    $html.='<div class="pkdrop">';

    $html.='<div class="pk-hero">
        <span class="pk-eyebrow">MY ACCOUNT · PK CHRONICLES</span>
        <h2>☠️ PK Item Drop History</h2>
        <p class="pk-muted">Historial de objetos que tus personajes han perdido al morir por PK.</p>
    </div>';

    $html.='<div class="pk-kpis">
        <div class="pk-kpi"><span>Total drops</span><strong class="red">'.number_format($total,0,',','.').'</strong></div>
        <div class="pk-kpi"><span>Últimos killers</span><strong>'.count($uniqueKillers).'</strong></div>
        <div class="pk-kpi"><span>Mapas recientes</span><strong>'.count($uniqueMaps).'</strong></div>
        <div class="pk-kpi"><span>Items con HEX</span><strong>'.$itemsWithHex.'</strong></div>
        <div class="pk-kpi"><span>Modo</span><strong class="green">READ ONLY</strong></div>
    </div>';

    $html.='<form class="pk-toolbar" method="get">
        <label>Personaje
            <select name="character">
                <option value="">Todos mis personajes</option>';

    foreach($owned as $name){
        $html.='<option value="'.$e($name).'" '.($filter===$name?'selected':'').'>'.$e($name).'</option>';
    }

    $html.='</select></label>
        <button class="pk-btn" type="submit">Aplicar filtro</button>';

    if($filter!==''){
        $html.='<a class="pk-btn" href="/account/modules/pk-item-drop-history-professional">Limpiar</a>';
    }

    $html.='<span class="pk-muted">Mostrando hasta '.$limit.' registros recientes</span>
    </form>';

    $html.='<div class="pk-section"><span class="pk-eyebrow">RECENT LOSSES</span><h3>Últimos objetos perdidos</h3>';

    if(!$history){
        $html.='<div class="pk-empty">
            <h3>💀 Sin drops PK registrados</h3>
            <p>No hay pérdidas registradas para los personajes de esta cuenta.</p>
        </div>';
    }else{
        $html.='<div class="pk-table-wrap"><table><thead><tr>
            <th>Víctima</th>
            <th>Killer</th>
            <th>Item</th>';

        if($cfgBool('show_item_details',true)) $html.='<th>Detalles</th>';
        if($cfgBool('show_map',true)) $html.='<th>Mapa / Posición</th>';
        if($cfgBool('show_accounts',false)) $html.='<th>Cuentas</th>';
        if($cfgBool('show_item_hex',false)) $html.='<th>Item HEX</th>';

        $html.='<th>Fecha</th></tr></thead><tbody>';

        foreach($history as $row){
            $victim=(string)($read($row,$cols,['VictimName','Victim','CharacterName'])??'—');
            $killer=(string)($read($row,$cols,['KillerName','Killer'])??'—');

            $itemName=$read($row,$cols,['ItemName','Name']);
            $itemIndex=$read($row,$cols,['ItemIndex','ItemID','ItemCode','Item']);
            $itemSection=$read($row,$cols,['ItemSection','Section','ItemType','Type']);

            if($itemName!==null && $itemName!==''){
                $itemLabel=(string)$itemName;
            }elseif($itemSection!==null && $itemIndex!==null){
                $itemLabel='Item '.$itemSection.':'.$itemIndex;
            }elseif($itemIndex!==null){
                $itemLabel='Item #'.$itemIndex;
            }else{
                $itemLabel='Item perdido';
            }

            $level=$read($row,$cols,['ItemLevel','Level']);
            $excellent=$read($row,$cols,['ItemExcellent','Excellent','ExcellentOption','ExceOption']);
            $luck=$read($row,$cols,['ItemLuck','Luck']);
            $skill=$read($row,$cols,['ItemSkill','Skill']);
            $option=$read($row,$cols,['ItemOption','Option','AdditionalOption','AddOption']);

            $details=[];
            if($level!==null) $details[]='+'.(int)$level;
            if($skill!==null) $details[]='Skill '.((int)$skill?'Sí':'No');
            if($luck!==null) $details[]='Luck '.((int)$luck?'Sí':'No');
            if($option!==null) $details[]='Option '.$e($option);
            if($excellent!==null) $details[]='Exc '.$e($excellent);

            $map=$read($row,$cols,['MapNumber','Map']);
            $x=$read($row,$cols,['MapX','PosX','X']);
            $y=$read($row,$cols,['MapY','PosY','Y']);
            $mapText=$mapName($map);
            if($x!==null && $y!==null) $mapText.=' ('.$x.', '.$y.')';

            $vAcc=$read($row,$cols,['VictimAccount','VictimAccountID','VictimAccountId','AccountID']);
            $kAcc=$read($row,$cols,['KillerAccount','KillerAccountID','KillerAccountId']);

            $hexRaw=$read($row,$cols,['ItemHex','ItemData','ItemBytes']);
            $hex=$toHex($hexRaw);

            $date=$read($row,$cols,['DropDate','CreatedAt','Date','EventDate','CreatedDate','LogDate']);

            $html.='<tr>
                <td class="pk-victim">'.$e($victim).'<div class="pk-detail"><span class="pk-badge">VICTIM</span></div></td>
                <td class="pk-killer">'.$e($killer).'</td>
                <td><span class="pk-item">'.$e($itemLabel).'</span></td>';

            if($cfgBool('show_item_details',true)){
                $html.='<td>'.($details?implode(' · ',$details):'—').'</td>';
            }

            if($cfgBool('show_map',true)){
                $html.='<td>'.$e($mapText).'</td>';
            }

            if($cfgBool('show_accounts',false)){
                $html.='<td><small>Victim: '.$e($vAcc??'—').'<br>Killer: '.$e($kAcc??'—').'</small></td>';
            }

            if($cfgBool('show_item_hex',false)){
                $html.='<td><div class="pk-hex">'.$e($hex!==''?$hex:'—').'</div></td>';
            }

            $html.='<td>'.$e($date!==null?$date:'—').'</td></tr>';
        }

        $html.='</tbody></table></div>';
    }

    $html.='<div class="pk-note">
        🔒 Esta página no borra, restaura ni mueve items. Solo muestra el historial registrado por el GameServer.
    </div>';

    $html.='</div></div>';

    return [
        'title'=>'PK Drop History',
        'html'=>$html
    ];
};
