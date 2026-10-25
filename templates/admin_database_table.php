<?php
use Omega\Core\Security;
$e=[Security::class,'e'];
$feature=$feature??[];
$tableInfo=$tableInfo??[];
$preview=$preview??[];
$columns=$tableInfo['columns']??[];
?>
<div class="admin-panel">
    <div class="admin-panel-head">
        <div>
            <span class="eyebrow">READ-ONLY TABLE EXPLORER</span>
            <h2><?= $e((string)($tableInfo['name']??'')) ?></h2>
            <p class="muted">
                <?= number_format((int)($tableInfo['row_count']??0),0,',','.') ?> filas ·
                <?= count($columns) ?> columnas
            </p>
        </div>
        <a class="btn" href="/admin/database-features/<?= $e((string)($feature['slug']??'')) ?>">← Volver</a>
    </div>

    <div class="notice-box">
        🔒 Vista de diagnóstico. No existe ninguna acción INSERT, UPDATE o DELETE en esta pantalla.
    </div>

    <h3 style="margin-top:18px">Estructura</h3>
    <div style="overflow:auto">
        <table class="admin-table">
            <thead>
            <tr>
                <th>#</th>
                <th>Columna</th>
                <th>Tipo</th>
                <th>Longitud</th>
                <th>Precisión</th>
                <th>Escala</th>
                <th>Nullable</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach($columns as $col): ?>
                <tr>
                    <td><?= (int)$col['ORDINAL_POSITION'] ?></td>
                    <td><code><?= $e((string)$col['COLUMN_NAME']) ?></code></td>
                    <td><?= $e((string)$col['DATA_TYPE']) ?></td>
                    <td><?= $e((string)($col['CHARACTER_MAXIMUM_LENGTH']??'')) ?></td>
                    <td><?= $e((string)($col['NUMERIC_PRECISION']??'')) ?></td>
                    <td><?= $e((string)($col['NUMERIC_SCALE']??'')) ?></td>
                    <td><?= $e((string)$col['IS_NULLABLE']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <h3 style="margin-top:22px">Vista previa — TOP 25</h3>

    <?php if($preview): ?>
        <?php $keys=array_keys($preview[0]); ?>
        <div style="overflow:auto;max-height:520px">
            <table class="admin-table">
                <thead>
                <tr>
                    <?php foreach($keys as $key): ?><th><?= $e((string)$key) ?></th><?php endforeach; ?>
                </tr>
                </thead>
                <tbody>
                <?php foreach($preview as $row): ?>
                    <tr>
                        <?php foreach($keys as $key):
                            $value=$row[$key]??null;
                            if(is_resource($value)){
                                $value='[BINARY/RESOURCE]';
                            }elseif(is_string($value) && strlen($value)>120){
                                $value=substr($value,0,117).'...';
                            }elseif($value===null){
                                $value='NULL';
                            }
                        ?>
                            <td style="max-width:320px;overflow-wrap:anywhere"><?= $e((string)$value) ?></td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="notice-box">La tabla existe, pero actualmente no contiene registros.</div>
    <?php endif; ?>
</div>
