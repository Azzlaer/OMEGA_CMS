<?php
use Omega\Core\Security; use Omega\Core\Csrf; $e=[Security::class,'e']; $s=$settings??[];
$hc=$hcaptchaDiagnostic??[];
?>
<div class="admin-panel"><form method="post" action="/admin/settings" class="omega-form admin-form">
<input type="hidden" name="_csrf" value="<?= $e(Csrf::token()) ?>">
<label>Nombre del sitio<input name="site_name" value="<?= $e($s['site_name']??'') ?>"></label>
<label>URL del sitio<input name="site_url" value="<?= $e($s['site_url']??'') ?>"></label>
<label>Descripción<textarea name="server_description" rows="4"><?= $e($s['server_description']??'') ?></textarea></label>
<label>Discord URL<input name="discord_url" value="<?= $e($s['discord_url']??'') ?>"></label>
<label>Nota de descargas<textarea name="downloads_note" rows="3"><?= $e($s['downloads_note']??'') ?></textarea></label>

<div class="admin-setting-feature">
    <div>
        <span class="eyebrow">SEGURIDAD WEB</span>
        <h3>🛡 hCaptcha Security Status</h3>
        <p>Estado de la protección anti-bot configurada mediante el archivo <code>.env</code>.</p>
    </div>

    <div class="admin-security-status-grid">
        <div class="admin-security-status <?= !empty($hc['enabled'])?'ok':'off' ?>">
            <span>Servicio</span>
            <strong><?= !empty($hc['enabled'])?'ACTIVO':'DESACTIVADO' ?></strong>
        </div>
        <div class="admin-security-status <?= !empty($hc['configured'])?'ok':'bad' ?>">
            <span>Claves</span>
            <strong><?= !empty($hc['configured'])?'CONFIGURADAS':'INCOMPLETAS' ?></strong>
        </div>
        <div class="admin-security-status <?= !empty($hc['curl_available'])?'ok':'bad' ?>">
            <span>PHP cURL</span>
            <strong><?= !empty($hc['curl_available'])?'DISPONIBLE':'NO DISPONIBLE' ?></strong>
        </div>
        <div class="admin-security-status <?= !empty($hc['ready'])?'ok':'bad' ?>">
            <span>Estado general</span>
            <strong><?= !empty($hc['ready'])?'PROTEGIDO':'REVISAR' ?></strong>
        </div>
    </div>

    <div class="admin-security-route-list">
        <span class="<?= !empty($hc['login'])?'enabled':'disabled' ?>">Login</span>
        <span class="<?= !empty($hc['register'])?'enabled':'disabled' ?>">Registro</span>
        <span class="<?= !empty($hc['recovery'])?'enabled':'disabled' ?>">Recuperación</span>
    </div>

    <div class="notice-box">
        La Sitekey y la Secret se leen directamente desde <code>.env</code>. OMEGA nunca muestra la Secret en el AdminCP.
        Login y Registro quedan protegidos automáticamente cuando <code>HCAPTCHA_ENABLED=true</code>.
    </div>
</div>

<div class="admin-setting-feature">
    <div>
        <span class="eyebrow">RANKINGS · GUILDS</span>
        <h3>🏰 Ranking Profesional de Clanes</h3>
        <p>
            Configura la vista profesional nativa que aparece en
            <code>/rankings?type=guilds</code>.
        </p>
    </div>

    <div class="design-form-grid">
        <label>Cantidad máxima de clanes
            <input type="number"
                   name="guild_professional_page_limit"
                   min="10"
                   max="200"
                   value="<?= (int)($s['guild_professional_page_limit']??100) ?>">
        </label>

        <label>Orden predeterminado
            <select name="guild_professional_default_sort">
                <?php foreach([
                    'resets'=>'Total Resets',
                    'members'=>'Miembros',
                    'score'=>'Guild Score',
                    'name'=>'Nombre'
                ] as $value=>$label): ?>
                    <option value="<?= $e($value) ?>"
                        <?= ($s['guild_professional_default_sort']??'resets')===$value?'selected':'' ?>>
                        <?= $e($label) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
    </div>

    <div class="design-form-grid">
        <label class="check-line">
            <input type="checkbox"
                   name="guild_professional_show_score"
                   value="1"
                   <?= ($s['guild_professional_show_score']??'1')==='1'?'checked':'' ?>>
            Mostrar Guild Score
        </label>

        <label class="check-line">
            <input type="checkbox"
                   name="guild_professional_show_notice"
                   value="1"
                   <?= ($s['guild_professional_show_notice']??'1')==='1'?'checked':'' ?>>
            Mostrar Notice
        </label>

        <label class="check-line">
            <input type="checkbox"
                   name="guild_professional_show_member_preview"
                   value="1"
                   <?= ($s['guild_professional_show_member_preview']??'1')==='1'?'checked':'' ?>>
            Mostrar preview de miembros
        </label>
    </div>

    <div class="notice-box">
        ✨ Esta vista reemplaza definitivamente al ranking clásico de Guilds.
        También puedes entrar usando <code>/guilds</code>.
    </div>
</div>

<label class="check-line"><input type="checkbox" name="maintenance_mode" value="1" <?= ($s['maintenance_mode']??'0')==='1'?'checked':'' ?>> <span>Modo mantenimiento</span></label>
<div class="admin-form-actions"><button class="btn btn-primary" type="submit">Guardar configuración</button></div>
</form></div>
