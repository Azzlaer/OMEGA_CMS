<?php
use Omega\Core\Security;
use Omega\Core\Csrf;
$e=[Security::class,'e'];
$account=$account??[]; $characters=$characters??[]; $status=$status??[];
$success=$success??null; $error=$error??null; $visualAssets=$visualAssets??[]; $moduleUserMenu=$moduleUserMenu??[]; $nameRenderer=$nameRenderer??null; $jewelBankAvailable=$jewelBankAvailable??false;
?>
<section class="page-hero account-hero"><div class="container account-hero-row">
<div><span class="eyebrow">ACCOUNT PANEL</span><h1>Hola, <?= $e($account['memb___id']??'') ?></h1><p>Administra tu cuenta y tus personajes.</p></div>
<div class="account-state <?= (int)($status['ConnectStat']??0)===1?'is-online':'' ?>">
<span><?= (int)($status['ConnectStat']??0)===1?'ONLINE':'OFFLINE' ?></span>
<small><?= $e($status['ServerName']??'Fuera del juego') ?></small>
</div>
</div></section>

<section class="section account-section"><div class="container">
<?php if($success): ?><div class="form-alert success wide"><?= $e($success) ?></div><?php endif; ?>
<?php if($error): ?><div class="form-alert error wide"><?= $e($error) ?></div><?php endif; ?>

<div class="account-overview">
    <div class="account-profile-card">
        <div class="account-avatar">Ω</div>
        <div><span>CUENTA</span><strong><?= $e($account['memb___id']??'') ?></strong><small><?= $e($account['mail_addr']??'') ?></small></div>
    </div>
    <div class="overview-stat"><span>VIP / Account Level</span><strong><?= (int)($account['AccountLevel']??0) ?></strong><small><?= (int)($account['AccountLevel']??0)>0?'Activo':'Cuenta normal' ?></small></div>
    <div class="overview-stat"><span>Horas jugadas</span><strong><?= number_format((int)($status['OnlineHours']??0)) ?></strong><small>horas registradas</small></div>
    <div class="overview-stat"><span>Personajes</span><strong><?= count($characters) ?>/5</strong><small>slots utilizados</small></div>
</div>

<div class="account-layout">
<div>
    <div class="section-heading account-heading"><div><span class="eyebrow">MIS HÉROES</span><h2>Personajes</h2></div></div>
    <?php if(!$characters): ?>
        <div class="empty-state compact-empty"><span class="empty-icon">⚔</span><h3>Aún no tienes personajes</h3><p>Entra al juego y crea tu primer personaje.</p></div>
    <?php else: ?>
    <div class="character-grid">
        <?php foreach($characters as $char): ?>
        <article class="character-card">
            <div class="char-top">
                <?php $charIcon=$visualAssets['class.'.(int)$char['Class']]??''; ?>
                <div class="char-class-icon <?= $charIcon?'has-image':'' ?>">
                    <?php if($charIcon): ?>
                        <img src="<?= $e($charIcon) ?>" alt="<?= $e(\Omega\GameServer\Drivers\MuEmu097kKayito\CharacterClass::name((int)$char['Class'])) ?>">
                    <?php else: ?>
                        <?= $e(\Omega\GameServer\Drivers\MuEmu097kKayito\CharacterClass::shortName((int)$char['Class'])) ?>
                    <?php endif; ?>
                </div>
                <div><h3><?= $nameRenderer?$nameRenderer->render((string)$char['Name'],['area'=>'account','class'=>(int)$char['Class']]):$e($char['Name']) ?></h3><span><?= $e(\Omega\GameServer\Drivers\MuEmu097kKayito\CharacterClass::name((int)$char['Class'])) ?></span></div>
                <strong>Lv. <?= number_format((int)$char['cLevel']) ?></strong>
            </div>
            <div class="char-stats">
                <div><span>Reset</span><b><?= number_format((int)$char['ResetCount']) ?></b></div>
                <div><span>G.Reset</span><b><?= number_format((int)$char['GrandResetCount']) ?></b></div>
                <div><span>Puntos</span><b><?= number_format((int)$char['LevelUpPoint']) ?></b></div>
                <div><span>PK</span><b><?= number_format((int)$char['PkLevel']) ?></b></div>
            </div>
            <div class="char-location">📍 <?= $e(\Omega\GameServer\Drivers\MuEmu097kKayito\Driver::mapName((int)$char['MapNumber'])) ?> · <?= (int)$char['MapPosX'] ?>,<?= (int)$char['MapPosY'] ?></div>
            <a class="btn btn-ghost full" href="/account/character/<?= rawurlencode((string)$char['Name']) ?>">Administrar</a>
        </article>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<aside class="account-sidebar">
    <div class="side-card">
        <h3>Mi cuenta</h3>
        <a href="/account/password"><span>🔐 Cambiar contraseña</span><span>→</span></a>
        <a href="/account/social-profile"><span>🌐 Mi Perfil Social</span><span>→</span></a>
        <?php if($jewelBankAvailable): ?>
        <a href="/account/jewel-bank"><span>💎 Mi Jewel Bank</span><span>→</span></a>
        <?php endif; ?>
        <a href="/rankings"><span>🏆 Ver rankings</span><span>→</span></a>

        <?php
        /*
         * Normalizador visual de módulos.
         *
         * Algunos módulos antiguos pudieron persistir emojis como "?" o "??"
         * debido a una conversión ANSI/SQL. El menú de Mi Cuenta no debe
         * depender de ese valor corrupto: usa un emoji conocido por slug/url.
         */
        $moduleEmojiMap=[
            'referrals'=>'🎁',
            'referral'=>'🎁',
            'name-effects'=>'✨',
            'name-effects-professional'=>'✨',
            'duel-history-professional'=>'⚔️',
            'vip-memberships'=>'👑',
            'vip-membership'=>'👑',
            'pk-item-drop-history-professional'=>'☠️',
            'staff-official'=>'🛡️',
            'inventory-vault-viewer-kayito'=>'🎒',
            'inventory-vault-viewer'=>'🎒',
            'market'=>'🛒',
            'marketplace'=>'🛒',
        ];

        $moduleLabelMap=[
            'referrals'=>'Mis Referidos y Premios',
            'referral'=>'Mis Referidos y Premios',
            'name-effects'=>'Efectos de Nombre',
            'name-effects-professional'=>'Efectos de Nombre',
            'duel-history-professional'=>'Duel History',
            'vip-memberships'=>'Membresías VIP',
            'vip-membership'=>'Membresías VIP',
            'pk-item-drop-history-professional'=>'PK Drop History',
            'staff-official'=>'Staff Oficial',
            'inventory-vault-viewer-kayito'=>'Inventario y Baúl',
            'inventory-vault-viewer'=>'Inventario y Baúl',
            'market'=>'Mercado',
            'marketplace'=>'Mercado',
        ];
        ?>

        <?php foreach($moduleUserMenu as $menu):
            $url=(string)($menu['url']??'');
            $slug=(string)($menu['slug']??'');

            // Si ModuleRuntime no entrega slug, lo obtenemos de /account/modules/{slug}.
            if($slug==='' && preg_match('#/account/modules/([a-z0-9_-]+)#i',$url,$mm)){
                $slug=strtolower((string)$mm[1]);
            }

            $icon=trim((string)($menu['icon']??''));
            $label=trim((string)($menu['label']??'Módulo'));

            // Elimina prefijos dañados del tipo ?, ??, ???.
            $label=preg_replace('/^\?+\s*/u','',$label) ?: $label;

            // Si el icono viene vacío/corrupto, utiliza uno estable por slug.
            if($icon==='' || preg_match('/^\?+$/u',$icon)){
                $icon=$moduleEmojiMap[$slug]??'🧩';
            }

            // Para módulos conocidos también normalizamos el nombre visible.
            if(isset($moduleLabelMap[$slug])){
                $label=$moduleLabelMap[$slug];
            }
        ?>
        <a href="<?= $e($url) ?>"><span><?= $e($icon) ?> <?= $e($label) ?></span><span>→</span></a>
        <?php endforeach; ?>

        <a class="disabled-link" href="#"><span>📜 Historial de acciones</span><small>próximamente</small></a>
    </div>
    <div class="side-card danger-card">
        <h3>Cerrar sesión</h3><p>Finaliza tu sesión web de forma segura.</p>
        <form method="post" action="/logout"><input type="hidden" name="_csrf" value="<?= $e(Csrf::token()) ?>"><button class="btn btn-ghost full" type="submit">Salir de mi cuenta</button></form>
    </div>
</aside>
</div>
</div></section>
