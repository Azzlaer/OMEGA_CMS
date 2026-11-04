<?php
use Omega\Core\Security;
use Omega\Core\Csrf;
$e=[Security::class,'e']; $error=$error??null; $old=$old??[]; $moduleAssets=$moduleAssets??[];
$hcaptchaEnabled=!empty($hcaptchaEnabled);
$hcaptchaSiteKey=trim((string)($hcaptchaSiteKey??''));
?>
<?php foreach($moduleAssets as $asset): ?>
<?php if(($asset['type']??'')==='css'): ?><link rel="stylesheet" href="<?= $e($asset['url']) ?>"><?php endif; ?>
<?php if(($asset['type']??'')==='js'): ?><script defer src="<?= $e($asset['url']) ?>"></script><?php endif; ?>
<?php endforeach; ?>
<section class="page-hero compact"><div class="container">
<span class="eyebrow">NUEVO GUERRERO</span><h1>Crear una cuenta</h1>
<p>Únete al reino de <?= $e($appName??'Omega MU') ?>.</p>
</div></section>
<section class="section auth-section"><div class="container auth-grid">
<div class="auth-card">
    <div class="auth-card-head"><span class="auth-icon small">✦</span><div><h2>Registro de cuenta</h2><p>Compatible directamente con MuOnline97.</p></div></div>
    <?php if($error): ?><div class="form-alert error"><?= $e($error) ?></div><?php endif; ?>
    <form method="post" action="/register" class="omega-form" autocomplete="off">
        <input type="hidden" name="_csrf" value="<?= $e(Csrf::token()) ?>">
        <div class="hp-field" aria-hidden="true"><label>Website<input name="website" tabindex="-1" autocomplete="off"></label></div>
        <label>Cuenta
            <input name="username" minlength="4" maxlength="10" pattern="[A-Za-z0-9]+" required value="<?= $e($old['username']??'') ?>" placeholder="4 a 10 letras/números">
        </label>
        <div class="form-two">
            <label>Contraseña
                <input type="password" name="password" minlength="6" maxlength="10" required placeholder="6 a 10 caracteres">
            </label>
            <label>Repetir contraseña
                <input type="password" name="password_confirmation" minlength="6" maxlength="10" required placeholder="Repite la contraseña">
            </label>
        </div>
        <label>Correo electrónico
            <input type="email" name="email" maxlength="50" required value="<?= $e($old['email']??'') ?>" placeholder="tu@email.com">
        </label>
        <label>ID personal / PIN
            <input name="personal_id" inputmode="numeric" minlength="7" maxlength="18" pattern="[0-9]+" required value="<?= $e($old['personal_id']??'') ?>" placeholder="7 a 18 números">
            <small>Guárdalo. Puede utilizarse para funciones sensibles de la cuenta.</small>
        </label>
        <div id="omega-register-module-fields"></div>
        <label class="check-line"><input type="checkbox" name="terms" value="1" required> <span>Acepto las reglas y condiciones del servidor.</span></label>

        <?php if($hcaptchaEnabled && $hcaptchaSiteKey!==''): ?>
        <div class="omega-hcaptcha-box">
            <div class="omega-hcaptcha-head">
                <span>🛡</span>
                <div><strong>Verificación anti-bot</strong><small>Protección hCaptcha activa para nuevos registros</small></div>
            </div>
            <div class="h-captcha" data-sitekey="<?= $e($hcaptchaSiteKey) ?>" data-theme="dark"></div>
        </div>
        <?php elseif($hcaptchaEnabled): ?>
        <div class="form-alert error">hCaptcha está activado pero falta configurar HCAPTCHA_SITEKEY.</div>
        <?php endif; ?>

        <button class="btn btn-primary full" type="submit">Crear cuenta</button>
    </form>
    <div class="auth-links">¿Ya tienes cuenta? <a href="/login">Ingresar</a></div>
</div>
<aside class="auth-side">
    <span class="eyebrow">MU ONLINE 0.97K</span><h3>Empieza desde Lorencia.</h3>
    <p>Tu cuenta será creada directamente en <strong>MuOnline97</strong> junto con las estructuras necesarias para personajes.</p>
    <div class="mini-stats">
        <div><strong>DK</strong><span>Dark Knight</span></div>
        <div><strong>DW</strong><span>Dark Wizard</span></div>
        <div><strong>FE</strong><span>Fairy Elf</span></div>
        <div><strong>MG</strong><span>Magic Gladiator</span></div>
    </div>
</aside>
</div></section>

<?php if($hcaptchaEnabled && $hcaptchaSiteKey!==''): ?>
<script src="https://js.hcaptcha.com/1/api.js?hl=es" async defer></script>
<?php endif; ?>
