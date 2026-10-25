<?php
use Omega\Core\Security; use Omega\Core\Csrf;
$e=[Security::class,'e']; $error=$error??null; $success=$success??null;
?>
<section class="page-hero compact"><div class="container"><a class="back-link" href="/account">← Volver a mi cuenta</a><span class="eyebrow">SEGURIDAD</span><h1>Cambiar contraseña</h1><p>Debes estar desconectado del juego.</p></div></section>
<section class="section"><div class="container narrow-container">
<div class="auth-card">
<?php if($error): ?><div class="form-alert error"><?= $e($error) ?></div><?php endif; ?>
<?php if($success): ?><div class="form-alert success"><?= $e($success) ?></div><?php endif; ?>
<form method="post" action="/account/password" class="omega-form">
<input type="hidden" name="_csrf" value="<?= $e(Csrf::token()) ?>">
<label>Contraseña actual<input type="password" name="current_password" required autocomplete="current-password"></label>
<label>Nueva contraseña<input type="password" name="new_password" minlength="6" maxlength="10" required autocomplete="new-password"></label>
<label>Repetir nueva contraseña<input type="password" name="new_password_confirmation" minlength="6" maxlength="10" required autocomplete="new-password"></label>
<button class="btn btn-primary full" type="submit">Actualizar contraseña</button>
</form>
</div></div></section>
