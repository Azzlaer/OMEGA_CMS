<?php
use Omega\Core\Security;
$e=[Security::class,'e'];
$rows=$rows??[];
$onlineCount=$onlineCount??0;
$visualAssets=$visualAssets??[]; $nameRenderer=$nameRenderer??null;
?>
<section class="page-hero online-hero">
<div class="container">
    <span class="eyebrow">SERVIDOR EN VIVO</span>
    <h1>Usuarios Online</h1>
    <p>Cuentas conectadas identificadas mediante MEMB_STAT y asociadas a sus personajes en Character.</p>
</div>
</section>

<section class="section online-section">
<div class="container">
    <div class="online-summary">
        <div class="online-pulse"><i></i></div>
        <div><span>JUGADORES CONECTADOS</span><strong><?= number_format((int)$onlineCount) ?></strong></div>
        <p>Actualizado directamente desde <code>MEMB_STAT.ConnectStat = 1</code>.</p>
    </div>

    <div class="online-grid">
    <?php foreach($rows as $r): ?>
        <article class="online-card">
            <?php if(!empty($r['CharacterName'])): ?>
            <?php $onlineIcon=$visualAssets['class.'.(int)$r['Class']]??''; ?>
            <div class="online-avatar class-<?= (int)$r['Class'] ?>">
                <?php if($onlineIcon): ?>
                    <img src="<?= $e($onlineIcon) ?>" alt="<?= $e(\Omega\GameServer\Drivers\MuEmu097kKayito\CharacterClass::name((int)$r['Class'])) ?>">
                <?php else: ?>
                    <?= $e(\Omega\GameServer\Drivers\MuEmu097kKayito\CharacterClass::shortName((int)$r['Class'])) ?>
                <?php endif; ?>
            </div>
            <div class="online-info">
                <div class="online-name">
                    <strong><?= $nameRenderer?$nameRenderer->render((string)$r['CharacterName'],['area'=>'online','class'=>(int)($r['Class']??-1)]):$e($r['CharacterName']) ?></strong>
                    <span><i></i> ONLINE</span>
                </div>
                <small><?= $e(\Omega\GameServer\Drivers\MuEmu097kKayito\CharacterClass::name((int)$r['Class'])) ?> · <?= $e(\Omega\GameServer\Drivers\MuEmu097kKayito\CharacterClass::evolutionLabel((int)$r['Class'])) ?></small>
                <div class="online-character-stats">
                    <div><span>Nivel</span><b><?= number_format((int)$r['cLevel']) ?></b></div>
                    <div><span>Reset</span><b><?= number_format((int)$r['ResetCount']) ?></b></div>
                    <div><span>G.Reset</span><b><?= number_format((int)$r['GrandResetCount']) ?></b></div>
                </div>
                <div class="online-location">
                    <span>📍 <?= $e(\Omega\GameServer\Drivers\MuEmu097kKayito\Driver::mapName((int)$r['MapNumber'])) ?></span>
                    <span><?= (int)$r['MapPosX'] ?>, <?= (int)$r['MapPosY'] ?></span>
                </div>
            </div>
            <?php else: ?>
            <div class="online-avatar unknown">?</div>
            <div class="online-info">
                <div class="online-name"><strong>Cuenta conectada</strong><span><i></i> ONLINE</span></div>
                <small>No fue posible asociar un personaje.</small>
            </div>
            <?php endif; ?>
        </article>
    <?php endforeach; ?>

    <?php if(!$rows): ?>
        <div class="empty-state online-empty">
            <span class="empty-icon">◌</span>
            <h3>No hay usuarios conectados</h3>
            <p>Cuando MEMB_STAT marque una cuenta con ConnectStat = 1 aparecerá aquí automáticamente.</p>
        </div>
    <?php endif; ?>
    </div>

    <div class="ranking-footnote online-privacy">
        <span>🛡</span>
        <p>Por privacidad, Omega utiliza el AccountID internamente solo para cruzar <strong>MEMB_STAT → Character</strong>. La página pública no muestra AccountID ni dirección IP.</p>
    </div>
</div>
</section>
