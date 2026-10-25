<?php
use Omega\Core\Security; use Omega\Core\Csrf;
$e=[Security::class,'e']; $char=$char??[]; $visualAssets=$visualAssets??[]; $nameRenderer=$nameRenderer??null;
?>
<section class="page-hero compact"><div class="container">
<a class="back-link" href="/account">← Volver a mi cuenta</a>
<?php $detailIcon=$visualAssets['class.'.(int)($char['Class']??0)]??''; ?>
<div class="character-hero-row">
    <?php if($detailIcon): ?>
    <div class="character-hero-icon">
        <img src="<?= $e($detailIcon) ?>" alt="<?= $e(\Omega\GameServer\Drivers\MuEmu097kKayito\CharacterClass::name((int)($char['Class']??0))) ?>">
    </div>
    <?php endif; ?>
    <div>
<span class="eyebrow">PERSONAJE</span><h1><?= $nameRenderer?$nameRenderer->render((string)($char['Name']??''),['area'=>'character','class'=>(int)($char['Class']??-1)]):$e($char['Name']??'') ?></h1>
<p><?= $e(\Omega\GameServer\Drivers\MuEmu097kKayito\CharacterClass::name((int)($char['Class']??0))) ?> · <?= $e(\Omega\GameServer\Drivers\MuEmu097kKayito\CharacterClass::evolutionLabel((int)($char['Class']??0))) ?> · Nivel <?= number_format((int)($char['cLevel']??0)) ?></p>
    </div>
</div>
</div></section>
<section class="section"><div class="container character-detail-grid">
<div>
<div class="detail-card">
    <div class="detail-head"><h2>Estadísticas</h2><span><?= number_format((int)$char['LevelUpPoint']) ?> puntos disponibles</span></div>
    <div class="stat-bars">
        <div><span>Fuerza</span><strong><?= number_format((int)$char['Strength']) ?></strong></div>
        <div><span>Agilidad</span><strong><?= number_format((int)$char['Dexterity']) ?></strong></div>
        <div><span>Vitalidad</span><strong><?= number_format((int)$char['Vitality']) ?></strong></div>
        <div><span>Energía</span><strong><?= number_format((int)$char['Energy']) ?></strong></div>
    </div>
    <div class="detail-meta">
        <div><span>Reset</span><strong><?= number_format((int)$char['ResetCount']) ?></strong></div>
        <div><span>Grand Reset</span><strong><?= number_format((int)$char['GrandResetCount']) ?></strong></div>
        <div><span>Zen</span><strong><?= number_format((int)$char['Money']) ?></strong></div>
        <div><span>Mapa</span><strong><?= $e(\Omega\GameServer\Drivers\MuEmu097kKayito\Driver::mapName((int)$char['MapNumber'])) ?></strong></div>
    </div>
</div>

<div class="detail-card">
    <div class="detail-head"><h2>Distribuir puntos</h2><span>Requiere estar offline</span></div>
    <form method="post" action="/account/character/<?= rawurlencode((string)$char['Name']) ?>/stats" class="omega-form">
        <input type="hidden" name="_csrf" value="<?= $e(Csrf::token()) ?>">
        <div class="form-two">
            <label>Fuerza<input type="number" name="strength" min="0" max="<?= (int)$char['LevelUpPoint'] ?>" value="0"></label>
            <label>Agilidad<input type="number" name="dexterity" min="0" max="<?= (int)$char['LevelUpPoint'] ?>" value="0"></label>
            <label>Vitalidad<input type="number" name="vitality" min="0" max="<?= (int)$char['LevelUpPoint'] ?>" value="0"></label>
            <label>Energía<input type="number" name="energy" min="0" max="<?= (int)$char['LevelUpPoint'] ?>" value="0"></label>
        </div>
        <button class="btn btn-primary" type="submit">Distribuir puntos</button>
    </form>
</div>
</div>

<aside class="character-actions">
    <div class="side-card">
        <h3>Acciones rápidas</h3>
        <p class="action-note">Las modificaciones del personaje se bloquean automáticamente mientras tu cuenta está conectada.</p>
        <form method="post" action="/account/character/<?= rawurlencode((string)$char['Name']) ?>/clearpk">
            <input type="hidden" name="_csrf" value="<?= $e(Csrf::token()) ?>">
            <button class="action-button" type="submit"><span>☠</span><div><b>Limpiar PK</b><small>Restaurar estado normal</small></div><i>→</i></button>
        </form>
        <form method="post" action="/account/character/<?= rawurlencode((string)$char['Name']) ?>/unstuck">
            <input type="hidden" name="_csrf" value="<?= $e(Csrf::token()) ?>">
            <button class="action-button" type="submit"><span>⌖</span><div><b>Destrabar personaje</b><small>Mover a Lorencia 125,125</small></div><i>→</i></button>
        </form>
        <button class="action-button disabled-action" type="button"><span>↻</span><div><b>Reset</b><small>Configurable en próxima fase</small></div><i>🔒</i></button>
        <button class="action-button disabled-action" type="button"><span>★</span><div><b>Grand Reset</b><small>Configurable en próxima fase</small></div><i>🔒</i></button>
    </div>
</aside>
</div></section>
