<?php
use Omega\Core\Security;
use Omega\Core\Csrf;

$e=[Security::class,'e'];
$rows=$rows??[];
$q=(string)($q??'');
$activeCount=(int)($activeCount??0);
$success=$success??null;
$error=$error??null;
?>
<div class="admin-panel">
    <div class="admin-panel-head">
        <div>
            <span class="eyebrow">RANKINGS</span>
            <h2>Personajes excluidos</h2>
            <p class="muted">
                Los personajes activos en esta lista no aparecerán en los rankings públicos,
                los Top de la portada ni los endpoints API de rankings.
            </p>
        </div>
        <div>
            <span class="status-pill"><?= $activeCount ?> exclusiones activas</span>
        </div>
    </div>

    <?php if($success): ?>
        <div class="notice-box success"><?= $e((string)$success) ?></div>
    <?php endif; ?>

    <?php if($error): ?>
        <div class="notice-box danger"><?= $e((string)$error) ?></div>
    <?php endif; ?>

    <div class="admin-grid two" style="margin-bottom:18px">
        <section class="admin-panel">
            <div class="admin-panel-head">
                <div>
                    <span class="eyebrow">AGREGAR</span>
                    <h3>Ocultar personajes</h3>
                </div>
            </div>

            <form method="post" action="/admin/ranking-exclusions/add" class="omega-form">
                <input type="hidden" name="_csrf" value="<?= $e(Csrf::token()) ?>">

                <label>
                    Personajes
                    <textarea name="characters" rows="8" maxlength="2000"
                              placeholder="Staff01&#10;GameMaster&#10;TestChar"></textarea>
                    <small>
                        Puedes escribir uno por línea o separarlos con coma o punto y coma.
                        Cada nombre se verifica contra la tabla <code>Character</code>.
                    </small>
                </label>

                <label>
                    Motivo / Nota interna
                    <input name="reason" maxlength="250"
                           placeholder="Ej: GM / personaje de pruebas / evento interno">
                </label>

                <button class="btn btn-primary" type="submit">
                    🚫 Agregar a exclusiones
                </button>
            </form>
        </section>

        <section class="admin-panel">
            <div class="admin-panel-head">
                <div>
                    <span class="eyebrow">ALCANCE</span>
                    <h3>Filtro global</h3>
                </div>
            </div>

            <div class="notice-box">
                <strong>Se aplica automáticamente a:</strong><br><br>
                ✓ Top Level / Reset / Grand Reset<br>
                ✓ Rankings por clase<br>
                ✓ Blood Castle / Devil Square<br>
                ✓ Duel / Sky Event<br>
                ✓ Monster Points / MiniGames<br>
                ✓ Golden Archer<br>
                ✓ Top Nivel / Top Reset de la portada<br>
                ✓ APIs públicas de rankings<br>
                ✓ Guild Ranking si el personaje excluido es el Guild Master
            </div>

            <div class="notice-box" style="margin-top:10px">
                <strong>No modifica los datos del juego.</strong><br>
                Solo evita que el personaje aparezca públicamente en los rankings.
                Desactivar una entrada hace que vuelva a mostrarse inmediatamente.
            </div>
        </section>
    </div>

    <section class="admin-panel">
        <div class="admin-panel-head">
            <div>
                <span class="eyebrow">LISTA</span>
                <h3>Exclusiones configuradas</h3>
            </div>

            <form method="get" action="/admin/ranking-exclusions" style="display:flex;gap:7px">
                <input name="q" value="<?= $e($q) ?>" maxlength="80"
                       placeholder="Buscar personaje o motivo">
                <button type="submit">Buscar</button>
                <?php if($q!==''): ?>
                    <a class="btn" href="/admin/ranking-exclusions">Limpiar</a>
                <?php endif; ?>
            </form>
        </div>

        <div style="overflow:auto">
            <table class="admin-table">
                <thead>
                <tr>
                    <th>Personaje</th>
                    <th>Estado</th>
                    <th>Motivo</th>
                    <th>Agregado por</th>
                    <th>Creado</th>
                    <th>Actualizado</th>
                    <th>Acciones</th>
                </tr>
                </thead>
                <tbody>

                <?php foreach($rows as $row): ?>
                    <tr>
                        <td><strong><?= $e((string)$row['character_name']) ?></strong></td>
                        <td>
                            <?= !empty($row['is_active'])
                                ? '<span style="color:#ff9a7a">🚫 Oculto</span>'
                                : '<span style="color:#7f93a3">○ Inactivo</span>' ?>
                        </td>

                        <td style="min-width:270px">
                            <form method="post" action="/admin/ranking-exclusions/update"
                                  style="display:grid;grid-template-columns:1fr auto;gap:7px">
                                <input type="hidden" name="_csrf" value="<?= $e(Csrf::token()) ?>">
                                <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">

                                <input name="reason" maxlength="250"
                                       value="<?= $e((string)($row['reason']??'')) ?>"
                                       placeholder="Sin motivo">

                                <label style="display:flex;gap:5px;align-items:center;white-space:nowrap">
                                    <input type="checkbox" name="active" value="1"
                                           <?= !empty($row['is_active'])?'checked':'' ?>>
                                    Activo
                                </label>

                                <button type="submit" style="grid-column:1/-1">
                                    Guardar cambios
                                </button>
                            </form>
                        </td>

                        <td><?= $e((string)($row['created_by']??'')) ?></td>
                        <td><?= $e((string)($row['created_at']??'')) ?></td>
                        <td><?= $e((string)($row['updated_at']??'')) ?></td>

                        <td>
                            <form method="post" action="/admin/ranking-exclusions/delete"
                                  onsubmit="return confirm('¿Eliminar esta exclusión? El personaje volverá a aparecer en los rankings.')">
                                <input type="hidden" name="_csrf" value="<?= $e(Csrf::token()) ?>">
                                <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                                <button class="btn btn-danger" type="submit">
                                    Eliminar
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>

                <?php if(!$rows): ?>
                    <tr>
                        <td colspan="7">
                            <?= $q!==''?'No se encontraron coincidencias.':'Todavía no hay personajes excluidos.' ?>
                        </td>
                    </tr>
                <?php endif; ?>

                </tbody>
            </table>
        </div>
    </section>
</div>
