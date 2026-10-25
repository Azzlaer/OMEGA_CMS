<?php
use Omega\Core\Security;
$e=[Security::class,'e'];

$type=$type ?? 'level';
$rows=$rows ?? [];
$availability=$availability ?? [];
$visualAssets=$visualAssets ?? []; $nameRenderer=$nameRenderer??null;
$guildProfessional=$guildProfessional??null;
$eventStats=$eventStats??null;

$tabs=[
 'level'=>['Nivel','Personajes'],
 'reset'=>['Reset','Personajes'],
 'grandreset'=>['Grand Reset','Personajes'],
 'guilds'=>['Guilds','Personajes'],
 'bloodcastle'=>['Blood Castle','Eventos'],
 'devilsquare'=>['Devil Square','Eventos'],
 'duel'=>['Duelos','Sistemas'],
 'skyevent'=>['Sky Event','Sistemas'],
 'monsterpoints'=>['Monster Points','Sistemas'],
 'minigames'=>['MiniGames','Sistemas'],
 'goldenarcher'=>['Golden Archer','Sistemas'],
 'dark-wizard'=>['Dark Wizard','Clases'],
 'soul-master'=>['Soul Master','Clases'],
 'dark-knight'=>['Dark Knight','Clases'],
 'blade-knight'=>['Blade Knight','Clases'],
 'fairy-elf'=>['Fairy Elf','Clases'],
 'muse-elf'=>['Muse Elf','Clases'],
 'magic-gladiator'=>['Magic Gladiator','Clases'],
];

$descriptions=[
 'level'=>'Los personajes con mayor nivel y experiencia.',
 'reset'=>'Los guerreros con más resets acumulados.',
 'grandreset'=>'Ranking de Grand Reset del servidor.',
 'guilds'=>'Directorio profesional de clanes, Guild Masters, miembros y resets acumulados.',
 'bloodcastle'=>'Hall of Fame de Blood Castle: score oficial, progreso y estado actual del personaje.',
 'devilsquare'=>'Hall of Fame de Devil Square: score oficial, progreso y estado actual del personaje.',
 'duel'=>'Clasificación PvP basada en Rating, victorias y rachas.',
 'skyevent'=>'Rendimiento histórico acumulado en Sky Event.',
 'monsterpoints'=>'Personajes con mayor saldo y puntos obtenidos por monstruos.',
 'minigames'=>'Ranking global acumulado de todos los MiniGames.',
 'goldenarcher'=>'Ranking de Renas y Stones del Golden Archer.',
 'dark-wizard'=>'Ranking exclusivo de Dark Wizard sin evolución.',
 'soul-master'=>'Ranking exclusivo de Soul Master evolucionados.',
 'dark-knight'=>'Ranking exclusivo de Dark Knight sin evolución.',
 'blade-knight'=>'Ranking exclusivo de Blade Knight evolucionados.',
 'fairy-elf'=>'Ranking exclusivo de Fairy Elf sin evolución.',
 'muse-elf'=>'Ranking exclusivo de Muse Elf evolucionadas.',
 'magic-gladiator'=>'Ranking exclusivo de Magic Gladiator.',
];
?>
<section class="page-hero rankings-hero">
<div class="container">
    <span class="eyebrow">HALL OF FAME</span>
    <h1>Rankings</h1>
    <p><?= $e($descriptions[$type] ?? 'Los mejores jugadores del servidor.') ?></p>
</div>
</section>

<section class="section rankings-section">
<div class="container">

<div class="ranking-groups">
<?php foreach(['Personajes','Clases','Eventos','Sistemas'] as $group): ?>
    <div class="ranking-group">
        <span class="ranking-group-label"><?= $e($group) ?></span>
        <div class="tabs ranking-tabs">
        <?php foreach($tabs as $key=>$tab):
            if($tab[1]!==$group) continue;
            if(array_key_exists($key,$availability) && !$availability[$key]) continue;
        ?>
            <a class="<?= $type===$key?'active':'' ?>" href="/rankings?type=<?= $e($key) ?>"><?= $e($tab[0]) ?></a>
        <?php endforeach; ?>
        </div>
    </div>
<?php endforeach; ?>
</div>

<?php if($type==='guilds'): ?>
<?php
$gp=$guildProfessional??[];
$gpGuilds=$gp['guilds']??[];
$gpStats=$gp['stats']??[];
$gpMembers=$gp['members']??[];
$gpSearch=(string)($gp['search']??'');
$gpSort=(string)($gp['sort']??'resets');
$gpAvailable=!empty($gp['available']);

$guildMarkHtml=static function(mixed $mark,string $name='Guild') use($e): string {
    if($mark===null){
        return '<div class="gpro-mark gpro-mark-empty" title="'.$e($name).'">🏰</div>';
    }

    if(is_resource($mark)){
        $data=stream_get_contents($mark);
        if($data===false) $data='';
    }elseif(is_string($mark)){
        $data=$mark;
    }else{
        $data='';
    }

    if($data===''){
        return '<div class="gpro-mark gpro-mark-empty" title="'.$e($name).'">🏰</div>';
    }

    if(strlen($data)>32){
        $hex=preg_replace('/\s+/','',$data);
        if($hex!==null && strlen($hex)>=64 && preg_match('/^[0-9A-Fa-f]+$/',$hex)){
            $decoded=@hex2bin(substr($hex,0,64));
            if($decoded!==false) $data=$decoded;
        }
    }

    if(strlen($data)<32){
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
        $byte=ord($data[$i]);

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
?>

<?php if(!$gpAvailable): ?>
    <div class="notice-box danger">
        🏰 <?= $e((string)($gp['error']??'No fue posible cargar el ranking profesional de clanes.')) ?>
    </div>
<?php else: ?>

<div class="guild-ranking-professional">
    <div class="gpro-header">
        <div>
            <span class="eyebrow">GUILD DIRECTORY</span>
            <h2>🏰 Clanes del Servidor</h2>
            <p>Ranking profesional integrado directamente en Omega.</p>
        </div>
        <span class="gpro-badge">PROFESSIONAL</span>
    </div>

    <div class="gpro-kpis">
        <div class="gpro-kpi">
            <span>Clanes</span>
            <strong><?= number_format((int)($gpStats['GuildCount']??0),0,',','.') ?></strong>
        </div>
        <div class="gpro-kpi">
            <span>Miembros</span>
            <strong><?= number_format((int)($gpStats['MemberCount']??0),0,',','.') ?></strong>
        </div>
        <div class="gpro-kpi">
            <span>Resets acumulados</span>
            <strong><?= number_format((int)($gpStats['TotalResets']??0),0,',','.') ?></strong>
        </div>
        <div class="gpro-kpi">
            <span>Top Guild Resets</span>
            <strong><?= number_format((int)($gp['top_reset']??0),0,',','.') ?></strong>
        </div>
    </div>

    <form method="get" action="/rankings" class="gpro-toolbar">
        <input type="hidden" name="type" value="guilds">

        <label>Buscar clan / master / notice
            <input name="q"
                   value="<?= $e($gpSearch) ?>"
                   placeholder="Nombre del clan...">
        </label>

        <label>Ordenar por
            <select name="sort">
                <option value="resets" <?= $gpSort==='resets'?'selected':'' ?>>Total Resets</option>
                <option value="members" <?= $gpSort==='members'?'selected':'' ?>>Miembros</option>
                <option value="score" <?= $gpSort==='score'?'selected':'' ?>>Guild Score</option>
                <option value="name" <?= $gpSort==='name'?'selected':'' ?>>Nombre</option>
            </select>
        </label>

        <button class="gpro-btn" type="submit">Aplicar</button>

        <?php if($gpSearch!=='' || $gpSort!=='resets'): ?>
            <a class="gpro-btn" href="/rankings?type=guilds">Limpiar</a>
        <?php endif; ?>

        <span class="gpro-info">
            Hasta <?= (int)($gp['limit']??100) ?> clanes · datos en tiempo real
        </span>
    </form>

    <?php if(!$gpGuilds): ?>
        <div class="gpro-empty">
            <h3>🏰 No se encontraron clanes</h3>
            <p>No existen coincidencias para el filtro aplicado.</p>
        </div>
    <?php else: ?>
        <div class="gpro-grid">
        <?php foreach($gpGuilds as $guild):
            $name=(string)($guild['GuildName']??'Sin nombre');
            $master=trim((string)($guild['GuildMaster']??''));
            $notice=trim((string)($guild['GuildNotice']??''));
            $score=(int)($guild['GuildScore']??0);
            $members=(int)($guild['MemberCount']??0);
            $resets=(int)($guild['TotalResets']??0);
        ?>
            <article class="gpro-card">
                <div class="gpro-card-head">
                    <?= $guildMarkHtml($guild['GuildMark']??null,$name) ?>

                    <div>
                        <div class="gpro-name"><?= $e($name) ?></div>
                        <div class="gpro-master">
                            👑 Guild Master:
                            <?php if($master!==''): ?>
                                <a href="/character/<?= rawurlencode($master) ?>">
                                    <strong><?= $e($master) ?></strong>
                                </a>
                            <?php else: ?>
                                <strong>—</strong>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if(!empty($gp['show_score'])): ?>
                    <div class="gpro-score">
                        <span>Guild Score</span>
                        <strong><?= number_format($score,0,',','.') ?></strong>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="gpro-stats">
                    <div class="gpro-stat">
                        <span>Miembros</span>
                        <strong>👥 <?= number_format($members,0,',','.') ?></strong>
                    </div>
                    <div class="gpro-stat">
                        <span>Total Resets</span>
                        <strong>♻ <?= number_format($resets,0,',','.') ?></strong>
                    </div>
                    <div class="gpro-stat">
                        <span>Promedio</span>
                        <strong><?= $members>0?number_format($resets/$members,1,',','.'):'0' ?></strong>
                    </div>
                </div>

                <?php if(!empty($gp['show_notice'])): ?>
                    <div class="gpro-notice">
                        📜 <?= $notice!==''?$e($notice):'Este clan no tiene notice.' ?>
                    </div>
                <?php endif; ?>

                <?php if(!empty($gp['show_member_preview']) && !empty($gpMembers[$name])): ?>
                    <div class="gpro-members">
                    <?php foreach($gpMembers[$name] as $member):
                        $memberName=(string)$member['Name'];
                    ?>
                        <a class="gpro-member"
                           href="/character/<?= rawurlencode($memberName) ?>">
                            ⚔ <?= $e($memberName) ?> · R
                            <?= number_format((int)$member['ResetCount'],0,',','.') ?>
                        </a>
                    <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="gpro-footnote">
        <span>Ω</span>
        <div>
            <strong>Total Resets</strong> se calcula en tiempo real usando
            <code>Character.ResetCount</code> de los personajes relacionados
            mediante <code>GuildMember</code>.
        </div>
    </div>
</div>
<?php endif; ?>

<?php else: ?>
<?php if(in_array($type,['bloodcastle','devilsquare'],true) && is_array($eventStats)): ?>
<div class="event-ranking-summary">
    <div class="event-stat-card"><span>Participantes</span><strong><?= number_format((int)($eventStats['participants']??0)) ?></strong><small>registrados</small></div>
    <div class="event-stat-card"><span>Mejor score</span><strong><?= number_format((int)($eventStats['bestScore']??0)) ?></strong><small>récord actual</small></div>
    <div class="event-stat-card"><span>Score promedio</span><strong><?= number_format((float)($eventStats['averageScore']??0),0) ?></strong><small>promedio global</small></div>
    <div class="event-stat-card event-stat-source"><span>Fuente</span><strong><?= $type==='bloodcastle'?'Blood Castle':'Devil Square' ?></strong><small>Name + Score</small></div>
</div>
<?php endif; ?>
<div class="ranking-titlebar">
    <div>
        <span class="eyebrow">TOP <?= count($rows) ?></span>
        <h2><?= $e($tabs[$type][0] ?? 'Ranking') ?></h2>
    </div>
    <span class="ranking-live-dot"><i></i> Datos de MuOnline97</span>
</div>

<div class="table-card advanced-ranking-table">
<table>
<thead>
<tr>
<th>#</th>

<?php if($type==='guilds'): ?>
    <th>Guild</th><th>Master</th><th>Score</th>

<?php elseif(in_array($type,['bloodcastle','devilsquare'],true)): ?>
    <th>Personaje</th><th>Estado</th><th>Clase</th><th>Nivel</th><th>Reset</th><th>G.Reset</th><th>Mapa</th><th>Score</th>

<?php elseif($type==='duel'): ?>
    <th>Personaje</th><th>Clase</th><th>Rating</th><th>Duelos</th><th>Victorias</th><th>Derrotas</th><th>K/D</th><th>Mejor racha</th>

<?php elseif($type==='skyevent'): ?>
    <th>Personaje</th><th>Eventos</th><th>Victorias</th><th>Kills</th><th>Score total</th><th>Mejor score</th>

<?php elseif($type==='monsterpoints'): ?>
    <th>Personaje</th><th>Saldo</th><th>Total ganado</th><th>Total gastado</th><th>Última actualización</th>

<?php elseif($type==='minigames'): ?>
    <th>Personaje</th><th>Participaciones</th><th>Victorias</th><th>Derrotas</th><th>Score</th><th>Latin Points</th><th>Zen ganado</th>

<?php elseif($type==='goldenarcher'): ?>
    <th>Personaje</th><th>Renas</th><th>Stones</th>

<?php elseif(str_starts_with($type,'class:')): ?>
    <th>Personaje</th><th>Clase</th><th>Estado</th><th>Nivel</th><th>Reset</th><th>G.Reset</th><th>PK</th><th>Mapa</th>

<?php elseif(in_array($type,['dark-wizard','soul-master','dark-knight','blade-knight','fairy-elf','muse-elf','magic-gladiator'],true)): ?>
    <th>Personaje</th><th>Clase</th><th>Estado</th><th>Nivel</th><th>Reset</th><th>G.Reset</th><th>PK</th><th>Mapa</th>

<?php else: ?>
    <th>Personaje</th><th>Clase</th><th>Nivel</th><th>Reset</th><th>G.Reset</th><th>PK</th>
<?php endif; ?>
</tr>
</thead>

<tbody>
<?php foreach($rows as $i=>$r): ?>
<tr class="<?= $i<3?'top-rank-row top-rank-'.($i+1):'' ?>">
<td>
    <span class="table-rank rank-<?= $i+1 ?>">
        <?= $i===0?'★':($i+1) ?>
    </span>
</td>

<?php if($type==='guilds'): ?>
    <td><strong><?= $e($r['G_Name']) ?></strong></td>
    <td>
        <?php if(!empty($r['G_Master'])): ?>
            <a class="rank-character-link" href="/character/<?= rawurlencode((string)$r['G_Master']) ?>">
                <?= $nameRenderer
                    ? $nameRenderer->render((string)$r['G_Master'],['area'=>'ranking-guild-master'])
                    : $e($r['G_Master']) ?>
            </a>
        <?php else: ?>-<?php endif; ?>
    </td>
    <td><strong><?= number_format((int)$r['G_Score']) ?></strong></td>

<?php elseif(in_array($type,['bloodcastle','devilsquare'],true)): ?>
    <?php $eventCharacterExists=array_key_exists('Class',$r) && $r['Class']!==null; ?>
    <td>
        <div class="rank-character">
            <?php $eventClass=$eventCharacterExists?(int)$r['Class']:-1; $icon=$visualAssets['class.'.$eventClass]??''; ?>
            <?php if($icon): ?><img class="rank-class-icon" src="<?= $e($icon) ?>" alt=""><?php endif; ?>
            <div>
                <strong><a class="rank-character-link" href="/character/<?= rawurlencode((string)$r['Name']) ?>"><?= $nameRenderer?$nameRenderer->render((string)$r['Name'],['area'=>'ranking-event','class'=>$eventClass]):$e($r['Name']) ?></a></strong>
                <?php if(!$eventCharacterExists): ?><small class="rank-sub">Registro histórico</small><?php endif; ?>
            </div>
        </div>
    </td>
    <td><?php if($eventCharacterExists): ?><span class="event-online-state <?= !empty($r['IsOnline'])?'online':'offline' ?>"><i></i><?= !empty($r['IsOnline'])?'Online':'Offline' ?></span><?php else: ?>—<?php endif; ?></td>
    <td><?= $eventCharacterExists?$e(\Omega\GameServer\Drivers\MuEmu097kKayito\CharacterClass::name((int)$r['Class'])):'—' ?></td>
    <td><?= $eventCharacterExists?number_format((int)$r['cLevel']):'—' ?></td>
    <td><?= $eventCharacterExists?number_format((int)$r['ResetCount']):'—' ?></td>
    <td><?= $eventCharacterExists?number_format((int)$r['GrandResetCount']):'—' ?></td>
    <td><?= $eventCharacterExists?$e(\Omega\GameServer\Drivers\MuEmu097kKayito\Driver::mapName((int)$r['MapNumber'])):'—' ?></td>
    <td><strong class="event-score-value"><?= number_format((int)$r['Score']) ?></strong></td>

<?php elseif($type==='duel'): ?>
    <td><strong><a class="rank-character-link" href="/character/<?= rawurlencode((string)$r['CharacterName']) ?>"><?= $nameRenderer?$nameRenderer->render((string)$r['CharacterName'],['area'=>'ranking','class'=>(int)($r['Class']??-1)]):$e($r['CharacterName']) ?></a></strong><small class="rank-sub">Lv. <?= number_format((int)$r['Level']) ?> · R<?= number_format((int)$r['ResetCount']) ?></small></td>
    <td><?= $e(\Omega\GameServer\Drivers\MuEmu097kKayito\CharacterClass::name((int)$r['Class'])) ?></td>
    <td><strong class="rank-highlight"><?= number_format((int)$r['Rating']) ?></strong></td>
    <td><?= number_format((int)$r['Duels']) ?></td>
    <td class="rank-win"><?= number_format((int)$r['Wins']) ?></td>
    <td class="rank-loss"><?= number_format((int)$r['Losses']) ?></td>
    <td><?= number_format((int)$r['Kills']) ?> / <?= number_format((int)$r['Deaths']) ?></td>
    <td><?= number_format((int)$r['BestStreak']) ?></td>

<?php elseif($type==='skyevent'): ?>
    <td><strong><a class="rank-character-link" href="/character/<?= rawurlencode((string)$r['CharacterName']) ?>"><?= $nameRenderer?$nameRenderer->render((string)$r['CharacterName'],['area'=>'ranking','class'=>(int)($r['Class']??-1)]):$e($r['CharacterName']) ?></a></strong></td>
    <td><?= number_format((int)$r['EventsPlayed']) ?></td>
    <td class="rank-win"><strong><?= number_format((int)$r['Wins']) ?></strong></td>
    <td><?= number_format((int)$r['TotalKills']) ?></td>
    <td><strong class="rank-highlight"><?= number_format((int)$r['TotalScore']) ?></strong></td>
    <td><?= number_format((int)$r['BestScore']) ?></td>

<?php elseif($type==='monsterpoints'): ?>
    <td><strong><a class="rank-character-link" href="/character/<?= rawurlencode((string)$r['CharacterName']) ?>"><?= $nameRenderer?$nameRenderer->render((string)$r['CharacterName'],['area'=>'ranking','class'=>(int)($r['Class']??-1)]):$e($r['CharacterName']) ?></a></strong></td>
    <td><strong class="rank-highlight"><?= number_format((int)$r['Points']) ?></strong></td>
    <td><?= number_format((int)$r['TotalEarned']) ?></td>
    <td><?= number_format((int)$r['TotalSpent']) ?></td>
    <td><?= !empty($r['LastUpdate']) ? $e(date('d/m/Y H:i',strtotime((string)$r['LastUpdate']))) : '-' ?></td>

<?php elseif($type==='minigames'): ?>
    <td><strong><a class="rank-character-link" href="/character/<?= rawurlencode((string)$r['CharacterName']) ?>"><?= $nameRenderer?$nameRenderer->render((string)$r['CharacterName'],['area'=>'ranking','class'=>(int)($r['Class']??-1)]):$e($r['CharacterName']) ?></a></strong></td>
    <td><?= number_format((int)$r['Participations']) ?></td>
    <td class="rank-win"><strong><?= number_format((int)$r['Wins']) ?></strong></td>
    <td class="rank-loss"><?= number_format((int)$r['Losses']) ?></td>
    <td><strong class="rank-highlight"><?= number_format((int)$r['TotalScore']) ?></strong></td>
    <td><?= number_format((int)$r['LatinPointsEarned']) ?></td>
    <td><?= number_format((int)$r['ZenEarned']) ?></td>

<?php elseif($type==='goldenarcher'): ?>
    <td><strong><a class="rank-character-link" href="/character/<?= rawurlencode((string)$r['CharacterName']) ?>"><?= $nameRenderer?$nameRenderer->render((string)$r['CharacterName'],['area'=>'ranking','class'=>(int)($r['Class']??-1)]):$e($r['CharacterName']) ?></a></strong></td>
    <td><strong class="rank-highlight"><?= number_format((int)$r['Renas']) ?></strong></td>
    <td><?= number_format((int)$r['Stones']) ?></td>

<?php elseif(in_array($type,['dark-wizard','soul-master','dark-knight','blade-knight','fairy-elf','muse-elf','magic-gladiator'],true)): ?>
    <td>
        <div class="rank-character">
            <?php $icon=$visualAssets['class.'.(int)$r['Class']]??''; ?>
            <?php if($icon): ?><img class="rank-class-icon" src="<?= $e($icon) ?>" alt=""><?php endif; ?>
            <strong><a class="rank-character-link" href="/character/<?= rawurlencode((string)$r['Name']) ?>"><?= $nameRenderer?$nameRenderer->render((string)$r['Name'],['area'=>'ranking','class'=>(int)($r['Class']??-1)]):$e($r['Name']) ?></a></strong>
        </div>
    </td>
    <td><?= $e(\Omega\GameServer\Drivers\MuEmu097kKayito\CharacterClass::name((int)$r['Class'])) ?></td>
    <td><span class="class-state <?= \Omega\GameServer\Drivers\MuEmu097kKayito\CharacterClass::isEvolved((int)$r['Class'])?'evolved':'base' ?>"><?= $e(\Omega\GameServer\Drivers\MuEmu097kKayito\CharacterClass::evolutionLabel((int)$r['Class'])) ?></span></td>
    <td><?= number_format((int)$r['cLevel']) ?></td>
    <td><strong class="rank-highlight"><?= number_format((int)$r['ResetCount']) ?></strong></td>
    <td><?= number_format((int)$r['GrandResetCount']) ?></td>
    <td><?= number_format((int)$r['PkLevel']) ?></td>
    <td><?= $e(\Omega\GameServer\Drivers\MuEmu097kKayito\Driver::mapName((int)$r['MapNumber'])) ?></td>

<?php else: ?>
    <td>
        <div class="rank-character">
            <?php $icon=$visualAssets['class.'.(int)$r['Class']]??''; ?>
            <?php if($icon): ?><img class="rank-class-icon" src="<?= $e($icon) ?>" alt=""><?php endif; ?>
            <strong><a class="rank-character-link" href="/character/<?= rawurlencode((string)$r['Name']) ?>"><?= $nameRenderer?$nameRenderer->render((string)$r['Name'],['area'=>'ranking','class'=>(int)($r['Class']??-1)]):$e($r['Name']) ?></a></strong>
        </div>
    </td>
    <td><?= $e(\Omega\GameServer\Drivers\MuEmu097kKayito\CharacterClass::name((int)$r['Class'])) ?></td>
    <td><?= number_format((int)$r['cLevel']) ?></td>
    <td><?= number_format((int)$r['ResetCount']) ?></td>
    <td><?= number_format((int)$r['GrandResetCount']) ?></td>
    <td><?= number_format((int)$r['PkLevel']) ?></td>
<?php endif; ?>
</tr>
<?php endforeach; ?>

<?php if(!$rows): ?>
<tr>
    <td colspan="10" class="empty-cell">
        <div class="ranking-empty">
            <strong>Aún no hay datos para este ranking.</strong>
            <span>El GameServer comenzará a mostrar jugadores aquí cuando existan registros en la tabla correspondiente.</span>
        </div>
    </td>
</tr>
<?php endif; ?>
</tbody>
</table>
</div>

<div class="ranking-footnote">
    <span>Ω</span>
    <p>Los rankings se consultan en tiempo real desde <strong>MuOnline97</strong>. Las cuentas administrativas y los IDs privados no se muestran públicamente.</p>
</div>


<?php endif; ?>
</div>
</section>
