<?php
use Omega\Core\Security;
$e=[Security::class,'e'];

$features=$features??[];
$stats=$stats??[];
?>
<div class="admin-panel">
    <div class="admin-panel-head">
        <div>
            <span class="eyebrow">DATABASE INTELLIGENCE</span>
            <h2>Database Feature Center</h2>
            <p class="muted">
                OmegaCMS inspecciona la base MuOnline97 y detecta sistemas que pueden convertirse
                en nuevas funciones web. Esta sección es completamente de solo lectura.
            </p>
        </div>
    </div>

    <div class="admin-grid" style="grid-template-columns:repeat(5,minmax(150px,1fr));margin-bottom:18px">
        <div class="admin-panel"><span class="eyebrow">TABLAS DB</span><h2><?= (int)($stats['table_count']??0) ?></h2></div>
        <div class="admin-panel"><span class="eyebrow">FEATURES</span><h2><?= (int)($stats['feature_count']??0) ?></h2></div>
        <div class="admin-panel"><span class="eyebrow">LISTOS</span><h2 style="color:#70d79e"><?= (int)($stats['ready']??0) ?></h2></div>
        <div class="admin-panel"><span class="eyebrow">PARCIALES</span><h2 style="color:#e4bd63"><?= (int)($stats['partial']??0) ?></h2></div>
        <div class="admin-panel"><span class="eyebrow">NO DETECTADOS</span><h2 style="color:#8795a0"><?= (int)($stats['missing']??0) ?></h2></div>
    </div>

    <div class="notice-box" style="margin-bottom:18px">
        <strong>🔒 Modo seguro:</strong> este explorador no modifica tablas del GameServer.
        Solo usa <code>INFORMATION_SCHEMA</code>, <code>COUNT(*)</code> y vistas previas
        <code>SELECT TOP 25</code>.
    </div>

    <div class="admin-grid two">
        <?php foreach($features as $f):
            $status=(string)$f['status'];
            $statusLabel=$status==='ready'?'✓ Detectado':($status==='partial'?'◐ Parcial':'○ No detectado');
            $statusColor=$status==='ready'?'#70d79e':($status==='partial'?'#e4bd63':'#8795a0');
        ?>
            <section class="admin-panel">
                <div class="admin-panel-head">
                    <div>
                        <span class="eyebrow"><?= $e(strtoupper((string)$f['priority'])) ?></span>
                        <h3><?= $e((string)$f['icon']) ?> <?= $e((string)$f['title']) ?></h3>
                    </div>
                    <strong style="color:<?= $statusColor ?>"><?= $statusLabel ?></strong>
                </div>

                <p><?= $e((string)$f['description']) ?></p>

                <div style="height:7px;background:#101820;border-radius:6px;overflow:hidden;margin:10px 0">
                    <div style="height:100%;width:<?= (int)$f['coverage'] ?>%;background:<?= $statusColor ?>"></div>
                </div>

                <small><?= (int)$f['coverage'] ?>% de tablas detectadas</small>

                <div class="notice-box" style="margin-top:12px">
                    <strong>Idea Omega:</strong><br>
                    <?= $e((string)$f['idea']) ?>
                </div>

                <p style="margin-top:12px">
                    <?php foreach($f['tables'] as $table): ?>
                        <?php $ok=in_array($table,$f['found'],true); ?>
                        <code style="display:inline-block;margin:2px;color:<?= $ok?'#83e8ad':'#8d99a2' ?>">
                            <?= $ok?'✓':'○' ?> <?= $e($table) ?>
                        </code>
                    <?php endforeach; ?>
                </p>

                <?php if($f['found']): ?>
                    <a class="btn btn-primary" href="/admin/database-features/<?= $e((string)$f['slug']) ?>">
                        Explorar sistema →
                    </a>
                <?php else: ?>
                    <button disabled>No instalado</button>
                <?php endif; ?>
            </section>
        <?php endforeach; ?>
    </div>
</div>
