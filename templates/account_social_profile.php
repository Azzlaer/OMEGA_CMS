<?php
use Omega\Core\Security;
use Omega\Core\Csrf;

$e=[Security::class,'e'];
$profile=$profile??[];
$countries=$countries??[];
$success=$success??null;
$error=$error??null;

$fields=[
    'discord'=>['icon'=>'💬','label'=>'Discord','placeholder'=>'Usuario o https://discord.gg/...'],
    'tiktok'=>['icon'=>'♪','label'=>'TikTok','placeholder'=>'https://www.tiktok.com/@usuario'],
    'youtube'=>['icon'=>'▶','label'=>'YouTube','placeholder'=>'https://youtube.com/@canal'],
    'website'=>['icon'=>'🌐','label'=>'Sitio web','placeholder'=>'https://tusitio.com'],
    'facebook'=>['icon'=>'f','label'=>'Facebook','placeholder'=>'https://facebook.com/...'],
    'instagram'=>['icon'=>'◎','label'=>'Instagram','placeholder'=>'https://instagram.com/...'],
    'whatsapp'=>['icon'=>'☎','label'=>'WhatsApp','placeholder'=>'+56 9 1234 5678'],
    'linkedin'=>['icon'=>'in','label'=>'LinkedIn','placeholder'=>'https://linkedin.com/in/...'],
];
?>
<section class="page-hero social-profile-hero">
<div class="container">
    <span class="eyebrow">MY ACCOUNT · SOCIAL PROFILE</span>
    <h1>🌐 Mi Perfil Social</h1>
    <p>Decide qué información quieres asociar públicamente a tus personajes.</p>
</div>
</section>

<section class="section">
<div class="container">

<?php if($success): ?><div class="form-alert success wide"><?= $e($success) ?></div><?php endif; ?>
<?php if($error): ?><div class="form-alert error wide"><?= $e($error) ?></div><?php endif; ?>

<div class="social-profile-layout">
    <div>
        <form method="post" action="/account/social-profile" enctype="multipart/form-data" class="social-profile-form">
            <input type="hidden" name="_csrf" value="<?= $e(Csrf::token()) ?>">

            <section class="social-card">
                <div class="social-card-head">
                    <div>
                        <span class="eyebrow">IDENTIDAD PÚBLICA</span>
                        <h2>Información básica</h2>
                    </div>
                </div>

                <div class="social-form-grid">
                    <label>
                        Nombre público / Alias
                        <input name="display_name" maxlength="50"
                               value="<?= $e((string)($profile['display_name']??'')) ?>"
                               placeholder="Ej: Andres / DarkLord / Skynet">
                        <small>No mostramos tu AccountID en la ficha pública.</small>
                    </label>

                    <label>
                        País
                        <select name="country_code">
                            <?php foreach($countries as $code=>$name): ?>
                                <option value="<?= $e((string)$code) ?>"
                                    <?= strtoupper((string)($profile['country_code']??''))===$code?'selected':'' ?>>
                                    <?= $e((string)$name) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                </div>

                <label>
                    Acerca de mí
                    <textarea name="bio" rows="4" maxlength="500"
                              placeholder="Una breve presentación para otros jugadores..."><?= $e((string)($profile['bio']??'')) ?></textarea>
                </label>
            </section>

            <section class="social-card">
                <div class="social-card-head">
                    <div>
                        <span class="eyebrow">BANNER</span>
                        <h2>Cabecera de tu perfil</h2>
                    </div>
                </div>

                <?php if(!empty($profile['banner_path'])): ?>
                    <div class="social-banner-preview">
                        <img src="<?= $e((string)$profile['banner_path']) ?>" alt="Banner actual">
                    </div>
                    <label class="social-check">
                        <input type="checkbox" name="remove_banner" value="1">
                        Eliminar banner actual
                    </label>
                <?php endif; ?>

                <label>
                    Subir nuevo banner
                    <input type="file" name="banner" accept="image/jpeg,image/png,image/webp">
                    <small>JPG, PNG o WEBP · máximo 4 MB · mínimo 600×160 px.</small>
                </label>
            </section>

            <section class="social-card">
                <div class="social-card-head">
                    <div>
                        <span class="eyebrow">REDES</span>
                        <h2>Cómo quieres que te encuentren</h2>
                    </div>
                </div>

                <div class="social-fields">
                    <?php foreach($fields as $key=>$meta): ?>
                    <div class="social-field-row">
                        <div class="social-brand-icon social-brand-<?= $e($key) ?>">
                            <?= $e($meta['icon']) ?>
                        </div>

                        <label>
                            <?= $e($meta['label']) ?>
                            <input name="<?= $e($key) ?>" maxlength="255"
                                   value="<?= $e((string)($profile[$key]??'')) ?>"
                                   placeholder="<?= $e($meta['placeholder']) ?>">
                        </label>

                        <label class="social-check">
                            <input type="checkbox" name="show_<?= $e($key) ?>" value="1"
                                <?= !empty($profile['show_'.$key])?'checked':'' ?>>
                            Mostrar públicamente
                        </label>
                    </div>
                    <?php endforeach; ?>
                </div>

                <div class="privacy-tip">
                    🔐 Los campos sin marcar como <strong>Mostrar públicamente</strong>
                    nunca aparecen en la ficha pública del personaje.
                </div>
            </section>

            <section class="social-card social-consent-card">
                <div class="social-card-head">
                    <div>
                        <span class="eyebrow">PRIVACIDAD Y CONSENTIMIENTO</span>
                        <h2>Publicación voluntaria</h2>
                    </div>
                </div>

                <div class="social-terms">
                    <p>
                        Al activar tu perfil público, entiendes que la información que marques
                        como visible podrá ser consultada desde Internet por otros jugadores,
                        copiada, compartida o utilizada para contactarte.
                    </p>

                    <p>
                        Publica únicamente datos que realmente quieras hacer públicos.
                        No publiques contraseñas, documentos de identidad, dirección particular,
                        datos bancarios, códigos de recuperación ni otra información sensible.
                    </p>

                    <p>
                        Puedes desactivar tu perfil o quitar una red social cuando quieras.
                        Eres responsable de los datos que decides publicar voluntariamente.
                        Este consentimiento no elimina las obligaciones legales del sitio respecto
                        de seguridad, privacidad y tratamiento adecuado de datos.
                    </p>
                </div>

                <label class="social-check important">
                    <input type="checkbox" name="consent_accepted" value="1"
                        <?= !empty($profile['consent_accepted'])?'checked':'' ?>>
                    He leído y acepto que la información que marque como pública
                    será visible en Internet.
                </label>

                <label class="social-check important public-toggle">
                    <input type="checkbox" name="public_enabled" value="1"
                        <?= !empty($profile['public_enabled'])?'checked':'' ?>>
                    🌍 Publicar mi perfil social en las fichas de mis personajes
                </label>

                <?php if(!empty($profile['consent_accepted_at'])): ?>
                    <small class="muted">
                        Consentimiento registrado: <?= $e((string)$profile['consent_accepted_at']) ?>
                    </small>
                <?php endif; ?>
            </section>

            <div class="social-savebar">
                <a class="btn btn-ghost" href="/account">← Mi Cuenta</a>
                <button class="btn btn-primary" type="submit">💾 Guardar perfil social</button>
            </div>
        </form>
    </div>

    <aside>
        <div class="side-card">
            <h3>👤 ¿Dónde aparecerá?</h3>
            <p>
                Cuando alguien pulse tu personaje desde un ranking verá una ficha
                pública del personaje y, si lo permites, una sección
                <strong>Propietario del personaje</strong>.
            </p>
        </div>

        <div class="side-card">
            <h3>🌍 Perfil opcional</h3>
            <p>
                Si no activas la publicación, tus personajes siguen apareciendo en
                los rankings normalmente pero no mostramos tus redes sociales.
            </p>
        </div>

        <div class="side-card">
            <h3>🛡 Recomendación</h3>
            <p>
                Para WhatsApp considera usar un número destinado a comunidad/juegos
                si no quieres publicar tu número personal.
            </p>
        </div>
    </aside>
</div>

</div>
</section>
