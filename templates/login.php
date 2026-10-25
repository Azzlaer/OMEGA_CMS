<?php
use Omega\Core\Security;
use Omega\Core\Csrf;
$e=[Security::class,'e'];
$error=$error??null; $success=$success??null;
?>
<section class="page-hero compact"><div class="container">
<span class="eyebrow">ACCOUNT PANEL</span><h1>Ingresar a mi cuenta</h1>
<p>Accede a tus personajes y herramientas del servidor.</p>
</div></section>
<section class="section auth-section"><div class="container auth-grid">
<div class="auth-card">
    <div class="auth-card-head"><span class="auth-icon small">Ω</span><div><h2>Bienvenido de vuelta</h2><p>Utiliza los mismos datos que usas dentro del juego.</p></div></div>
    <?php if($error): ?><div class="form-alert error"><?= $e($error) ?></div><?php endif; ?>
    <?php if($success): ?><div class="form-alert success"><?= $e($success) ?></div><?php endif; ?>
    <form method="post" action="/login" class="omega-form" autocomplete="on">
        <input type="hidden" name="_csrf" value="<?= $e(Csrf::token()) ?>">
        <label>Cuenta
            <input name="username" maxlength="10" required autocomplete="username" value="<?= $e($old['username']??'') ?>" placeholder="Tu cuenta">
        </label>
        <label>Contraseña
            <input type="password" name="password" maxlength="20" required autocomplete="current-password" placeholder="••••••••">
        </label>
        <button class="btn btn-primary full" type="submit">Ingresar</button>
    </form>
    <div class="auth-links"><a href="/register">Crear cuenta</a><span>·</span><a href="/account/recovery">Recuperar contraseña</a></div>
</div>
<aside class="auth-side">
    <span class="eyebrow">SEGURIDAD</span><h3>Tu cuenta, bajo control.</h3>
    <ul class="feature-list">
        <li><b>Sesiones seguras</b><span>Cookie HttpOnly + SameSite + regeneración de sesión.</span></li>
        <li><b>Protección CSRF</b><span>Las operaciones sensibles requieren token.</span></li>
        <li><b>Anti brute-force</b><span>Los intentos de acceso se limitan y registran.</span></li>
        <li><b>Operaciones offline</b><span>No modificamos personajes mientras están conectados.</span></li>
    </ul>
</aside>
</div></section>
