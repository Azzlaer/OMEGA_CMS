<?php
use Omega\Core\Security;
use Omega\GameServer\Drivers\MuEmu097kKayito\CharacterClass;
use Omega\GameServer\Drivers\MuEmu097kKayito\Driver;

$e=[Security::class,'e'];
$char=$char??[];
$ownerProfile=$ownerProfile??null;
$visualAssets=$visualAssets??[];
$nameRenderer=$nameRenderer??null;

$class=(int)($char['Class']??0);
$charName=(string)($char['Name']??'');
$classImage=$visualAssets['class.'.$class]??'';
?>
<section class="public-character-hero <?= !empty($ownerProfile['banner_path'])?'has-owner-banner':'' ?>"
    <?php if(!empty($ownerProfile['banner_path'])): ?>
        style="--profile-banner:url('<?= $e((string)$ownerProfile['banner_path']) ?>')"
    <?php endif; ?>>
<div class="container public-character-hero-inner">
    <div class="public-character-avatar <?= $classImage?'has-image':'' ?>">
        <?php if($classImage): ?>
            <img src="<?= $e($classImage) ?>" alt="">
        <?php else: ?>
            <?= $e(CharacterClass::shortName($class)) ?>
        <?php endif; ?>
    </div>

    <div>
        <span class="eyebrow">PUBLIC CHARACTER PROFILE</span>
        <h1><?= $nameRenderer
            ? $nameRenderer->render($charName,['area'=>'public-character','class'=>$class])
            : $e($charName) ?></h1>
        <p><?= $e(CharacterClass::name($class)) ?> · <?= $e(Driver::mapName((int)($char['MapNumber']??0))) ?></p>
    </div>
</div>
</section>

<section class="section">
<div class="container">

<div class="public-character-stats">
    <div><span>Nivel</span><strong><?= number_format((int)($char['cLevel']??0)) ?></strong></div>
    <div><span>Reset</span><strong><?= number_format((int)($char['ResetCount']??0)) ?></strong></div>
    <div><span>Grand Reset</span><strong><?= number_format((int)($char['GrandResetCount']??0)) ?></strong></div>
    <div><span>PK Level</span><strong><?= number_format((int)($char['PkLevel']??0)) ?></strong></div>
    <div><span>Guild</span><strong><?= $e((string)($char['G_Name']??'Sin guild')) ?></strong></div>
</div>

<div class="public-character-layout">
    <div>
        <section class="public-profile-card">
            <span class="eyebrow">PERSONAJE</span>
            <h2>⚔ Información del héroe</h2>

            <div class="public-stat-grid">
                <div><span>Strength</span><b><?= number_format((int)($char['Strength']??0)) ?></b></div>
                <div><span>Dexterity</span><b><?= number_format((int)($char['Dexterity']??0)) ?></b></div>
                <div><span>Vitality</span><b><?= number_format((int)($char['Vitality']??0)) ?></b></div>
                <div><span>Energy</span><b><?= number_format((int)($char['Energy']??0)) ?></b></div>
                <div><span>LevelUpPoint</span><b><?= number_format((int)($char['LevelUpPoint']??0)) ?></b></div>
                <div><span>PK Count</span><b><?= number_format((int)($char['PkCount']??0)) ?></b></div>
            </div>

            <div class="public-location">
                📍 <?= $e(Driver::mapName((int)($char['MapNumber']??0))) ?>
                · <?= (int)($char['MapPosX']??0) ?>, <?= (int)($char['MapPosY']??0) ?>
            </div>
        </section>
    </div>

    <aside>
        <?php if($ownerProfile): ?>
        <section class="public-profile-card owner-profile-card">
            <span class="eyebrow">PROPIETARIO DEL PERSONAJE</span>

            <div class="owner-profile-head">
                <div class="owner-country-flag"><?= $e((string)$ownerProfile['country_flag']) ?></div>
                <div>
                    <h2><?= $e((string)($ownerProfile['display_name']?:'Jugador de Omega MU')) ?></h2>
                    <p><?= $e((string)($ownerProfile['country_name']?:'País no indicado')) ?></p>
                </div>
            </div>

            <?php if(!empty($ownerProfile['bio'])): ?>
                <p class="owner-bio"><?= nl2br($e((string)$ownerProfile['bio'])) ?></p>
            <?php endif; ?>

            <?php if(!empty($ownerProfile['socials'])): ?>
                <div class="public-social-links">
                <?php foreach($ownerProfile['socials'] as $social): ?>
                    <?php if(!empty($social['url'])): ?>
                        <a class="public-social social-<?= $e((string)$social['key']) ?>"
                           href="<?= $e((string)$social['url']) ?>"
                           target="_blank" rel="noopener noreferrer nofollow">
                            <span><?= $e((string)$social['icon']) ?></span>
                            <?= $e((string)$social['label']) ?>
                        </a>
                    <?php else: ?>
                        <div class="public-social social-<?= $e((string)$social['key']) ?>">
                            <span><?= $e((string)$social['icon']) ?></span>
                            <?= $e((string)$social['label']) ?>:
                            <?= $e((string)$social['value']) ?>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="muted">Este jugador no publicó redes sociales.</p>
            <?php endif; ?>

            <div class="public-owner-note">
                🌍 Esta información fue publicada voluntariamente por el propietario de la cuenta.
            </div>
        </section>
        <?php else: ?>
        <section class="public-profile-card owner-private-card">
            <span class="eyebrow">PROPIETARIO DEL PERSONAJE</span>
            <h2>🔒 Perfil privado</h2>
            <p>
                El propietario de este personaje no ha habilitado un perfil social público.
            </p>
        </section>
        <?php endif; ?>
    </aside>
</div>

<div class="ranking-footnote">
    <span>Ω</span>
    <p>
        La ficha del personaje se obtiene de MuOnline97.
        Los datos sociales solo aparecen cuando el propietario los publica voluntariamente.
        El AccountID nunca se muestra.
    </p>
</div>

</div>
</section>
