<?php
use Omega\Core\Security;
use Omega\Services\MyJewelBank;

$e=[Security::class,'e'];
$accountId=(string)($accountId??'');
$balance=$balance??[];
$history=$history??[];
$totals=$totals??[];
$recent=$recent??[];
$ledgerAvailable=$ledgerAvailable??false;

$jewels=[
    'Bless'=>['icon'=>'💎','label'=>'Jewel of Bless'],
    'Soul'=>['icon'=>'🔮','label'=>'Jewel of Soul'],
    'Life'=>['icon'=>'❤️','label'=>'Jewel of Life'],
    'Creation'=>['icon'=>'✨','label'=>'Jewel of Creation'],
    'Chaos'=>['icon'=>'🌀','label'=>'Jewel of Chaos'],
];
?>
<section class="page-hero account-hero jewel-bank-hero">
    <div class="container account-hero-row">
        <div>
            <span class="eyebrow">MY ACCOUNT · JEWEL BANK</span>
            <h1>💎 Mi Jewel Bank</h1>
            <p>Consulta tus jewels almacenados y los movimientos registrados por el servidor.</p>
        </div>

        <div class="account-state is-online">
            <span>READ ONLY</span>
            <small>Consulta segura</small>
        </div>
    </div>
</section>

<section class="section account-section">
<div class="container">

    <div class="notice-box" style="margin-bottom:18px">
        <strong>🔒 Modo seguro:</strong>
        esta página únicamente consulta el Jewel Bank. No realiza depósitos, retiros,
        entregas de items ni modificaciones en tu cuenta.
    </div>

    <div class="account-overview jewel-bank-overview">
        <div class="account-profile-card">
            <div class="account-avatar">💎</div>
            <div>
                <span>CUENTA</span>
                <strong><?= $e($accountId) ?></strong>
                <small>Custom Jewel Bank</small>
            </div>
        </div>

        <div class="overview-stat">
            <span>Total almacenado</span>
            <strong><?= number_format((int)($totals['total_jewels']??0),0,',','.') ?></strong>
            <small>jewels</small>
        </div>

        <div class="overview-stat">
            <span>Tipos con saldo</span>
            <strong><?= (int)($totals['types_with_balance']??0) ?>/5</strong>
            <small>tipos de jewel</small>
        </div>

        <div class="overview-stat">
            <span>Movimientos</span>
            <strong><?= number_format((int)($totals['ledger_count']??0),0,',','.') ?></strong>
            <small>registros históricos</small>
        </div>
    </div>

    <div class="section-heading account-heading">
        <div>
            <span class="eyebrow">BALANCE</span>
            <h2>Mis jewels almacenados</h2>
        </div>
        <a class="btn btn-ghost" href="/account">← Volver a Mi Cuenta</a>
    </div>

    <div class="character-grid jewel-bank-grid">
        <?php foreach($jewels as $key=>$meta): ?>
        <article class="character-card jewel-bank-card">
            <div class="char-top">
                <div class="char-class-icon"><?= $meta['icon'] ?></div>
                <div>
                    <h3><?= $e($meta['label']) ?></h3>
                    <span><?= $e($key) ?></span>
                </div>
            </div>

            <div style="padding:18px 0 8px;text-align:center">
                <strong style="font-size:34px;line-height:1;color:var(--gold,#d6b35a)">
                    <?= number_format((int)($balance[$key]??0),0,',','.') ?>
                </strong>
                <div class="muted" style="margin-top:7px">almacenados</div>
            </div>
        </article>
        <?php endforeach; ?>
    </div>

    <div class="account-layout" style="margin-top:22px">
        <div>
            <div class="section-heading account-heading">
                <div>
                    <span class="eyebrow">LEDGER</span>
                    <h2>Últimos movimientos</h2>
                </div>
            </div>

            <?php if(!$ledgerAvailable): ?>
                <div class="empty-state compact-empty">
                    <span class="empty-icon">📜</span>
                    <h3>Historial no disponible</h3>
                    <p>La tabla CustomJewelBankLedger no está instalada.</p>
                </div>
            <?php elseif(!$history): ?>
                <div class="empty-state compact-empty">
                    <span class="empty-icon">💎</span>
                    <h3>Sin movimientos registrados</h3>
                    <p>Cuando el servidor registre operaciones de Jewel Bank aparecerán aquí.</p>
                </div>
            <?php else: ?>
                <div class="admin-panel" style="overflow:auto">
                    <table class="admin-table">
                        <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Acción</th>
                            <th>Jewel</th>
                            <th>Cantidad</th>
                            <th>Request ID</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach($history as $row):
                            $amount=(int)($row['amount']??0);
                        ?>
                            <tr>
                                <td><?= $e((string)($row['created_at']??'—')) ?></td>
                                <td><?= $e(MyJewelBank::actionLabel($row['action']??null)) ?></td>
                                <td><?= $e(MyJewelBank::jewelLabel($row['jewel']??null)) ?></td>
                                <td>
                                    <strong style="color:<?= $amount<0?'#ff8b7b':'#78dca4' ?>">
                                        <?= $amount>0?'+':'' ?><?= number_format($amount,0,',','.') ?>
                                    </strong>
                                </td>
                                <td><code><?= $e((string)($row['request']??'—')) ?></code></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <aside class="account-sidebar">
            <div class="side-card">
                <h3>Resumen</h3>

                <div class="char-stats" style="grid-template-columns:1fr 1fr">
                    <div>
                        <span>Últimos +</span>
                        <b><?= number_format((int)($recent['recent_in']??0),0,',','.') ?></b>
                    </div>
                    <div>
                        <span>Últimos -</span>
                        <b><?= number_format((int)($recent['recent_out']??0),0,',','.') ?></b>
                    </div>
                </div>

                <p class="muted" style="margin-top:12px">
                    El resumen de entradas/salidas usa los últimos 10 registros del ledger.
                </p>
            </div>

            <div class="side-card">
                <h3>Sincronización</h3>
                <p>
                    Revision:
                    <strong><?= number_format((int)($balance['Revision']??0),0,',','.') ?></strong>
                </p>
                <p>
                    Última actualización:<br>
                    <strong><?= $e((string)($balance['LastUpdate']??'Sin información')) ?></strong>
                </p>
            </div>

            <div class="side-card">
                <h3>Seguridad</h3>
                <p class="muted">
                    OmegaCMS no ejecuta los procedimientos de depósito/retiro desde esta pantalla.
                    El balance mostrado proviene directamente de la base del GameServer.
                </p>
            </div>
        </aside>
    </div>
</div>
</section>
