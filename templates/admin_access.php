<?php
use Omega\Core\Security;
use Omega\Core\Csrf;
$e=[Security::class,'e'];
$rows=$rows??[];
$currentAccount=(string)($currentAccount??'');
$success=$success??null;
$error=$error??null;
?>
<div class="admin-panel">
    <div class="admin-panel-head">
        <div>
            <span class="eyebrow">SEGURIDAD</span>
            <h2>Lista blanca del AdminCP</h2>
            <p class="muted">
                Solo las cuentas MU activas que aparecen aquí pueden acceder a <code>/admin</code>.
                La autenticación usa la cuenta normal del juego; no existe una segunda contraseña de administrador.
            </p>
        </div>
    </div>

    <?php if($success): ?><div class="notice-box success"><?= $e((string)$success) ?></div><?php endif; ?>
    <?php if($error): ?><div class="notice-box danger"><?= $e((string)$error) ?></div><?php endif; ?>

    <div class="admin-grid two" style="margin-bottom:18px">
        <section class="admin-panel">
            <div class="admin-panel-head">
                <div><span class="eyebrow">WHITELIST</span><h3>Agregar colaborador</h3></div>
            </div>

            <form method="post" action="/admin/access/add" class="omega-form">
                <input type="hidden" name="_csrf" value="<?= $e(Csrf::token()) ?>">

                <label>
                    Cuenta MU / AccountID
                    <input name="account_id" maxlength="10" pattern="[A-Za-z0-9]+" required
                           placeholder="Ej: Ayudante01">
                </label>

                <label>
                    Rol
                    <select name="role">
                        <option value="admin">Administrador</option>
                        <option value="superadmin">Super Administrador</option>
                    </select>
                </label>

                <div class="notice-box">
                    <strong>Administrador:</strong> puede entrar y modificar el AdminCP,
                    pero no puede administrar esta lista blanca.<br><br>
                    <strong>Super Administrador:</strong> también puede agregar, desactivar
                    o quitar otros administradores.
                </div>

                <button class="btn btn-primary" type="submit">＋ Agregar a la lista blanca</button>
            </form>
        </section>

        <section class="admin-panel">
            <div class="admin-panel-head">
                <div><span class="eyebrow">PROTECCIÓN</span><h3>Reglas de seguridad</h3></div>
            </div>
            <div class="notice-box">
                ✓ La cuenta debe existir en <code>MEMB_INFO</code>.<br>
                ✓ No se guarda otra contraseña.<br>
                ✓ Solo un <strong>superadmin</strong> administra esta página.<br>
                ✓ No puedes quitarte tu propio acceso de superadmin.<br>
                ✓ El último superadmin activo está protegido.<br>
                ✓ Altas, cambios y bajas quedan registradas en <code>cms_audit_log</code>.
            </div>
        </section>
    </div>

    <div class="admin-panel">
        <div class="admin-panel-head">
            <div>
                <span class="eyebrow">AUTORIZADOS</span>
                <h3>Administradores con acceso</h3>
            </div>
        </div>

        <div style="overflow:auto">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Cuenta MU</th>
                    <th>Email</th>
                    <th>Cuenta existe</th>
                    <th>Rol</th>
                    <th>Estado</th>
                    <th>Último acceso</th>
                    <th>Creado</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach($rows as $r):
                $account=(string)($r['account_id']??'');
                $isSelf=strcasecmp($account,$currentAccount)===0;
            ?>
                <tr>
                    <td>
                        <strong><?= $e($account) ?></strong>
                        <?php if($isSelf): ?><small style="display:block">Tu cuenta</small><?php endif; ?>
                    </td>
                    <td><?= $e((string)($r['email']??'')) ?></td>
                    <td><?= !empty($r['game_account_exists'])?'✓ Sí':'✕ No' ?></td>
                    <td><?= $e((string)($r['role']??'')) ?></td>
                    <td><?= !empty($r['is_active'])?'🟢 Activo':'🔴 Desactivado' ?></td>
                    <td><?= $e((string)($r['last_login_at']??'Nunca')) ?></td>
                    <td><?= $e((string)($r['created_at']??'')) ?></td>
                    <td class="admin-actions-cell">
                        <form method="post" action="/admin/access/update" style="display:grid;gap:6px;min-width:180px">
                            <input type="hidden" name="_csrf" value="<?= $e(Csrf::token()) ?>">
                            <input type="hidden" name="account_id" value="<?= $e($account) ?>">
                            <select name="role" <?= $isSelf?'disabled':'' ?>>
                                <option value="admin" <?= strtolower((string)$r['role'])==='admin'?'selected':'' ?>>Administrador</option>
                                <option value="superadmin" <?= strtolower((string)$r['role'])==='superadmin'?'selected':'' ?>>Super Administrador</option>
                            </select>
                            <?php if($isSelf): ?>
                                <input type="hidden" name="role" value="superadmin">
                                <input type="hidden" name="active" value="1">
                                <button type="button" disabled>Cuenta protegida</button>
                            <?php else: ?>
                                <label style="display:flex;gap:6px;align-items:center">
                                    <input type="checkbox" name="active" value="1" <?= !empty($r['is_active'])?'checked':'' ?>>
                                    Activo
                                </label>
                                <button type="submit">Guardar</button>
                            <?php endif; ?>
                        </form>

                        <?php if(!$isSelf): ?>
                        <form method="post" action="/admin/access/remove"
                              onsubmit="return confirm('¿Quitar esta cuenta de la lista blanca del AdminCP?')"
                              style="margin-top:6px">
                            <input type="hidden" name="_csrf" value="<?= $e(Csrf::token()) ?>">
                            <input type="hidden" name="account_id" value="<?= $e($account) ?>">
                            <button class="btn btn-danger" type="submit">Quitar acceso</button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>

            <?php if(!$rows): ?>
                <tr><td colspan="8">No hay administradores registrados.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
        </div>
    </div>
</div>
