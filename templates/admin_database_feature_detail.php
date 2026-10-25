<?php
use Omega\Core\Security;
$e=[Security::class,'e'];
$feature=$feature??[];
$tables=$tables??[];
?>
<div class="admin-panel">
    <div class="admin-panel-head">
        <div>
            <span class="eyebrow">DATABASE FEATURE</span>
            <h2><?= $e((string)($feature['icon']??'')) ?> <?= $e((string)($feature['title']??'')) ?></h2>
            <p class="muted"><?= $e((string)($feature['description']??'')) ?></p>
        </div>
        <a class="btn" href="/admin/database-features">← Volver</a>
    </div>

    <div class="notice-box">
        <strong>Posible implementación:</strong>
        <?= $e((string)($feature['idea']??'')) ?>
    </div>

    <?php if(!empty($feature['missing'])): ?>
        <div class="notice-box" style="margin-top:10px">
            <strong>Tablas no encontradas:</strong>
            <?= $e(implode(', ',$feature['missing'])) ?>
        </div>
    <?php endif; ?>

    <div class="admin-grid two" style="margin-top:18px">
        <?php foreach($tables as $table): ?>
            <section class="admin-panel">
                <div class="admin-panel-head">
                    <div>
                        <span class="eyebrow">TABLE</span>
                        <h3><?= $e((string)$table['name']) ?></h3>
                    </div>
                    <strong><?= number_format((int)$table['row_count'],0,',','.') ?> filas</strong>
                </div>

                <p class="muted"><?= count($table['columns']) ?> columnas detectadas.</p>

                <div style="max-height:210px;overflow:auto">
                    <table class="admin-table">
                        <thead><tr><th>Columna</th><th>Tipo</th><th>NULL</th></tr></thead>
                        <tbody>
                        <?php foreach($table['columns'] as $col): ?>
                            <tr>
                                <td><code><?= $e((string)$col['COLUMN_NAME']) ?></code></td>
                                <td><?= $e((string)$col['DATA_TYPE']) ?></td>
                                <td><?= $e((string)$col['IS_NULLABLE']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <a class="btn btn-primary" style="margin-top:12px"
                   href="/admin/database-features/<?= $e((string)$feature['slug']) ?>/table/<?= $e((string)$table['name']) ?>">
                    Ver estructura y datos →
                </a>
            </section>
        <?php endforeach; ?>
    </div>

    <?php if(!$tables): ?>
        <div class="notice-box">No hay tablas instaladas para este sistema.</div>
    <?php endif; ?>
</div>
