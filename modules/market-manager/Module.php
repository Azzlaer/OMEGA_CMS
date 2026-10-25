<?php
declare(strict_types=1);

require_once __DIR__.'/MarketManagerService.php';

return static function(string $hook,array $payload,array $config,array $context): mixed {
    /** @var \Omega\Core\Database $muDb */
    $muDb=$context['mu_db'];
    $market=new OmegaMarketManagerService($muDb);
    $e=static fn(string $v):string=>htmlspecialchars($v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');

    if($hook==='site.head'){
        return
            '<link rel="stylesheet" href="/module-assets/market-manager/assets/css/marketmanager.css?v=100">'.
            '<script defer src="/module-assets/market-manager/assets/js/marketmanager.js?v=100"></script>';
    }

    if($hook==='admin.action'){
        $action=(string)($payload['action']??'');
        $d=(array)($payload['data']??[]);

        if($action==='save_settings'){
            $market->saveSettings($d);
            return 'Configuración del mercado guardada.';
        }
        if($action==='currency_save'){
            $market->saveCurrency($d);
            return 'Moneda validada y guardada.';
        }
        if($action==='runtime_check'){
            return 'Runtime Market Manager Professional v1.0.1 activo.';
        }
        if($action==='diagnostics'){
            $parts=[];
            foreach($market->diagnostics() as $label=>$entry){
                if(is_array($entry)){
                    $status=(string)($entry['status']??'review');
                    $text=match($status){
                        'ok'=>'OK',
                        'safe'=>'SEGURO/OFF',
                        'active'=>'ACTIVO',
                        default=>'REVISAR'
                    };
                    $detail=trim((string)($entry['detail']??''));
                    $parts[]=$label.': '.$text.($detail!==''?' ('.$detail.')':'');
                }else{
                    $parts[]=$label.': '.($entry?'OK':'REVISAR');
                }
            }
            return implode(' | ',$parts);
        }
        return null;
    }

    if($hook==='admin.panel'){
        $s=$market->settings();
        $currencies=$market->currencies(false);
        $diag=$market->diagnostics();
        $token=$e((string)($payload['csrf_token']??''));
        $checked=static fn(string $key,bool $default=false): string =>
            (($s[$key]??($default?'1':'0'))==='1')?' checked':'';

        ob_start(); ?>
<style>
.mkadm-grid{display:grid;grid-template-columns:390px 1fr;gap:14px}
.mkadm-box{background:#11171e;border:1px solid #293440;border-radius:10px;padding:15px}
.mkadm-box h3{font:20px Georgia,serif;color:#e1bc59;margin:0 0 12px}
.mkadm-form{display:grid;gap:9px}
.mkadm-form label{font-size:10px;color:#aab5c0}
.mkadm-form input,.mkadm-form select,.mkadm-form textarea{width:100%;margin-top:4px;padding:8px;background:#0a1016;color:#e7edf3;border:1px solid #344153;border-radius:6px}
.mkadm-check{display:flex;gap:6px;align-items:center}.mkadm-check input{width:auto}
.mkadm-two{display:grid;grid-template-columns:1fr 1fr;gap:8px}
.mkadm-currency{padding:12px;border:1px solid #2d3945;border-radius:8px;background:#0d1319;margin-bottom:10px}
.mkadm-warning{padding:11px;border:1px solid #805d1e;background:#251d0e;color:#ebcf82;border-radius:7px;margin-bottom:12px}
.mkadm-diag{display:grid;gap:6px}.mkadm-diag div{display:flex;justify-content:space-between;padding:7px;border-bottom:1px solid #28313b}
@media(max-width:950px){.mkadm-grid{grid-template-columns:1fr}.mkadm-two{grid-template-columns:1fr}}
</style>
<div class="mkadm-grid">
<section class="mkadm-box">
<h3>🛒 Market Manager Professional</h3>
<div class="mkadm-warning"><strong>Modo seguro:</strong> publicaciones y compras vienen desactivadas. Ejecuta el diagnóstico y prueba primero con cuentas de laboratorio.</div>

<form method="post" action="/admin/modules/action" class="mkadm-form">
<input type="hidden" name="_csrf" value="<?= $token ?>">
<input type="hidden" name="slug" value="market-manager">
<input type="hidden" name="module_action" value="save_settings">

<label class="mkadm-check"><input type="checkbox" name="enabled" value="1"<?= $checked('enabled') ?>> Mercado visible y activo</label>
<label class="mkadm-check"><input type="checkbox" name="allow_listing" value="1"<?= $checked('allow_listing') ?>> Permitir publicaciones</label>
<label class="mkadm-check"><input type="checkbox" name="allow_purchase" value="1"<?= $checked('allow_purchase') ?>> Permitir compras</label>
<label class="mkadm-check"><input type="checkbox" name="require_offline" value="1"<?= $checked('require_offline',true) ?>> Exigir cuenta desconectada</label>
<label class="mkadm-check"><input type="checkbox" name="safe_claim_1x1_only" value="1"<?= $checked('safe_claim_1x1_only',true) ?>> Retiro automático seguro solo 1x1</label>
<label class="mkadm-check"><input type="checkbox" name="debug_mode" value="1"<?= $checked('debug_mode') ?>> Diagnóstico</label>
<label>Mensaje mantenimiento<input name="maintenance_message" value="<?= $e((string)$s['maintenance_message']) ?>"></label>
<div class="mkadm-two">
<label>Límite publicaciones<input type="number" min="1" max="100" name="listing_limit" value="<?= (int)$s['listing_limit'] ?>"></label>
<label>Duración días<input type="number" min="1" max="365" name="listing_duration_days" value="<?= (int)$s['listing_duration_days'] ?>"></label>
</div>
<button class="btn btn-primary">Guardar configuración</button>
</form>

<h3 style="margin-top:18px">Diagnóstico</h3>
<div class="mkadm-diag">
<?php foreach($diag as $label=>$entry):
$status=is_array($entry)?(string)($entry['status']??'review'):($entry?'ok':'review');
$detail=is_array($entry)?(string)($entry['detail']??''):'';
$badge=match($status){
    'ok'=>'✓ OK',
    'safe'=>'🛡 SEGURO / OFF',
    'active'=>'● ACTIVO',
    default=>'⚠ REVISAR'
};
?>
<div>
    <span><?= $e($label) ?><?php if($detail!==''): ?><small style="display:block;color:#778797;margin-top:3px"><?= $e($detail) ?></small><?php endif; ?></span>
    <strong><?= $e($badge) ?></strong>
</div>
<?php endforeach; ?>
</div>
</section>

<section class="mkadm-box">
<h3>Monedas</h3>
<?php foreach($currencies as $c): ?>
<div class="mkadm-currency">
<form method="post" action="/admin/modules/action" class="mkadm-form">
<input type="hidden" name="_csrf" value="<?= $token ?>">
<input type="hidden" name="slug" value="market-manager">
<input type="hidden" name="module_action" value="currency_save">
<input type="hidden" name="CurrencyCode" value="<?= $e((string)$c['CurrencyCode']) ?>">
<div class="mkadm-two">
<label>Nombre<input name="DisplayName" value="<?= $e((string)$c['DisplayName']) ?>"></label>
<label>Icono<input name="CurrencyIcon" value="<?= $e((string)($c['CurrencyIcon']??'💰')) ?>"></label>
<label>Tabla<input name="SourceTable" value="<?= $e((string)$c['SourceTable']) ?>"></label>
<label>Cuenta<input name="AccountColumn" value="<?= $e((string)$c['AccountColumn']) ?>"></label>
<label>Saldo<input name="BalanceColumn" value="<?= $e((string)$c['BalanceColumn']) ?>"></label>
<label>Mínimo<input type="number" name="MinimumPrice" value="<?= (int)$c['MinimumPrice'] ?>"></label>
<label>Máximo<input type="number" name="MaximumPrice" value="<?= (int)$c['MaximumPrice'] ?>"></label>
<label>Comisión %<input type="number" step="0.01" min="0" max="100" name="FeePercent" value="<?= $e((string)$c['FeePercent']) ?>"></label>
</div>
<label>Descripción<input name="Description" value="<?= $e((string)($c['Description']??'')) ?>"></label>
<label class="mkadm-check"><input type="checkbox" name="IsEnabled" value="1" <?= (int)$c['IsEnabled']?'checked':'' ?>> Moneda activa</label>
<button class="btn btn-primary">Validar y guardar moneda</button>
</form>
</div>
<?php endforeach; ?>

<h3>Agregar moneda</h3>
<form method="post" action="/admin/modules/action" class="mkadm-form">
<input type="hidden" name="_csrf" value="<?= $token ?>">
<input type="hidden" name="slug" value="market-manager">
<input type="hidden" name="module_action" value="currency_save">
<div class="mkadm-two">
<label>Código<input name="CurrencyCode" placeholder="WCOINC" required></label>
<label>Nombre<input name="DisplayName" placeholder="WCoinC" required></label>
<label>Icono<input name="CurrencyIcon" value="💰"></label>
<label>Tabla<input name="SourceTable" placeholder="CashShopData" required></label>
<label>Cuenta<input name="AccountColumn" placeholder="AccountID" required></label>
<label>Saldo<input name="BalanceColumn" placeholder="WCoinC" required></label>
<label>Mínimo<input type="number" name="MinimumPrice" value="1"></label>
<label>Máximo<input type="number" name="MaximumPrice" value="2000000000"></label>
<label>Comisión %<input type="number" step="0.01" name="FeePercent" value="0"></label>
</div>
<label>Descripción<input name="Description"></label>
<button class="btn btn-primary">Crear y validar</button>
</form>
</section>
</div>
        <?php return (string)ob_get_clean();
    }

    if($hook==='user.page'){
        $account=trim((string)($payload['account_id']??''));
        $method=strtoupper((string)($payload['method']??'GET'));
        $query=(array)($payload['query']??[]);
        $post=(array)($payload['post']??[]);
        $ip=(string)($_SERVER['REMOTE_ADDR']??'');
        $s=$market->settings();
        $message='';$error='';

        if($method==='POST'){
            try{
                if(isset($post['market_buy'])){
                    $market->buy($account,(int)($post['listing_id']??0),$ip);
                    $message='Compra completada. El objeto quedó protegido en tu Bóveda de Entregas.';
                }elseif(isset($post['market_sell'])){
                    $market->createListing(
                        $account,(int)($post['slot']??-1),(int)($post['price']??0),
                        (string)($post['currency']??''),$ip
                    );
                    $message='Objeto publicado correctamente.';
                }elseif(isset($post['market_cancel'])){
                    $market->cancelListing($account,(int)($post['listing_id']??0),$ip);
                    $message='Publicación cancelada. El objeto quedó en tu Bóveda de Entregas.';
                }elseif(isset($post['market_claim'])){
                    $market->claimDelivery($account,(int)($post['delivery_id']??0),$ip);
                    $message='Objeto retirado al Warehouse.';
                }
            }catch(\Throwable $ex){
                $error=$ex->getMessage();
            }
        }

        $view=(string)($query['view']??'market');
        if(!in_array($view,['market','sell','deliveries','mine'],true)) $view='market';

        $currencies=$market->currencies(true);
        $pending=$market->pendingDeliveryCount($account);

        ob_start(); ?>
<div class="we-market">
<header class="we-market-hero">
<div><span class="we-market-kicker">COMERCIO ENTRE JUGADORES</span><h1>🛒 Mercado Web</h1>
<p>Las compras quedan protegidas primero en una Bóveda de Entregas.</p></div>
</header>

<nav class="we-market-tabs">
<a href="?view=market" class="<?= $view==='market'?'active':'' ?>">Mercado</a>
<a href="?view=sell" class="<?= $view==='sell'?'active':'' ?>">Publicar</a>
<a href="?view=mine" class="<?= $view==='mine'?'active':'' ?>">Mis publicaciones</a>
<a href="?view=deliveries" class="<?= $view==='deliveries'?'active':'' ?>">Entregas (<?= $pending ?>)</a>
</nav>

<?php if($message!==''): ?><div class="we-market-message success"><?= $e($message) ?></div><?php endif; ?>
<?php if($error!==''): ?><div class="we-market-message error"><?= $e($error) ?></div><?php endif; ?>

<?php if(!$market->enabled()): ?><div class="we-market-notice"><?= $e((string)$s['maintenance_message']) ?></div><?php endif; ?>

<?php if($view==='market'):
$q=trim((string)($query['q']??''));
$currency=trim((string)($query['currency']??''));
$rows=$market->listings($q,$currency,max(1,(int)($query['page']??1)),24);
?>
<form class="we-market-filter" method="get">
<input type="hidden" name="view" value="market">
<input type="search" name="q" value="<?= $e($q) ?>" placeholder="Objeto, vendedor o serial...">
<select name="currency"><option value="">Todas las monedas</option>
<?php foreach($currencies as $c): ?><option value="<?= $e((string)$c['CurrencyCode']) ?>" <?= $currency===$c['CurrencyCode']?'selected':'' ?>><?= $e((string)($c['CurrencyIcon']?:'💰').' '.$c['DisplayName']) ?></option><?php endforeach; ?>
</select>
<button>Buscar</button>
</form>

<div class="we-market-grid">
<?php if(!$rows): ?><div class="we-market-empty">No hay publicaciones.</div><?php endif; ?>
<?php foreach($rows as $item): ?>
<article class="we-market-card">
<div class="we-market-item-icon"><span>⚔️</span></div>
<div class="we-market-card-body">
<h3><?= $e((string)$item['ItemName']) ?></h3>
<div class="we-market-meta"><span>Tipo <?= (int)$item['ItemType'] ?>:<?= (int)$item['ItemIndex'] ?></span><span>+<?= (int)$item['ItemLevel'] ?></span></div>
<div class="we-market-item-flags">
<?php if(!empty($item['DecodedItem']['skill'])): ?><span>Skill</span><?php endif; ?>
<?php if(!empty($item['DecodedItem']['luck'])): ?><span>Luck</span><?php endif; ?>
<?php if(!empty($item['DecodedItem']['excellent'])): ?><span class="excellent">Excellent</span><?php endif; ?>
</div>
<div class="we-market-serial">ID: <?= $e((string)$item['ObjectKey']) ?></div>
<div class="we-market-seller">Vendedor: <?= $e((string)$item['SellerAccount']) ?></div>
<div class="we-market-price"><?= number_format((int)$item['Price']) ?> <?= $e((string)$item['CurrencyCode']) ?></div>
<form method="post"><input type="hidden" name="_csrf" value="<?= $e((string)($payload['csrf_token']??'')) ?>"><input type="hidden" name="listing_id" value="<?= (int)$item['ListingId'] ?>"><button name="market_buy" value="1" <?= (!$market->enabled()||($s['allow_purchase']??'0')!=='1')?'disabled':'' ?>>Comprar</button></form>
</div>
</article>
<?php endforeach; ?>
</div>

<?php elseif($view==='sell'):
try{$slots=$market->warehouseSlots($account);}catch(\Throwable $ex){$slots=[];$error=$ex->getMessage();}
?>
<div class="we-market-safe-note">Para publicar debes estar desconectado. El objeto se retira del Warehouse dentro de una transacción y queda protegido por la publicación.</div>
<div class="we-warehouse-grid">
<?php foreach($slots as $item): ?>
<button type="button" class="we-warehouse-item" data-slot="<?= (int)$item['slot'] ?>" data-name="<?= $e((string)$item['name']) ?>" data-serial="<?= $e((string)$item['object_key']) ?>">
<strong><?= $e((string)$item['name']) ?> +<?= (int)$item['level'] ?></strong>
<span>Tipo <?= (int)$item['type'] ?>:<?= (int)$item['id'] ?></span>
<small><?= $e((string)$item['object_key']) ?></small>
</button>
<?php endforeach; ?>
</div>
<form method="post" class="we-market-sell-form">
<input type="hidden" name="_csrf" value="<?= $e((string)($payload['csrf_token']??'')) ?>">
<h3 id="we-market-selection">Selecciona un objeto del Warehouse</h3>
<label>Slot<input id="we-market-slot" name="slot" readonly required></label>
<label>Precio<input type="number" min="1" name="price" required></label>
<label>Moneda<select name="currency" required><?php foreach($currencies as $c): ?><option value="<?= $e((string)$c['CurrencyCode']) ?>"><?= $e((string)$c['DisplayName']) ?></option><?php endforeach; ?></select></label>
<button name="market_sell" value="1" <?= (!$market->enabled()||($s['allow_listing']??'0')!=='1')?'disabled':'' ?>>Publicar</button>
</form>

<?php elseif($view==='mine'):
$mine=$market->myListings($account);
?>
<div class="we-market-table-wrap"><table class="we-market-table"><thead><tr><th>ID</th><th>Objeto</th><th>Precio</th><th>Estado</th><th></th></tr></thead><tbody>
<?php foreach($mine as $m): ?><tr><td>#<?= (int)$m['ListingId'] ?></td><td><?= $e((string)$m['ItemName']) ?></td><td><?= number_format((int)$m['Price']) ?> <?= $e((string)$m['CurrencyCode']) ?></td><td><?= $e((string)$m['Status']) ?></td><td><?php if($m['Status']==='ACTIVE'): ?><form method="post"><input type="hidden" name="_csrf" value="<?= $e((string)($payload['csrf_token']??'')) ?>"><input type="hidden" name="listing_id" value="<?= (int)$m['ListingId'] ?>"><button name="market_cancel" value="1">Cancelar</button></form><?php endif; ?></td></tr><?php endforeach; ?>
</tbody></table></div>

<?php else:
$status=strtoupper(trim((string)($query['status']??'')));
$deliveries=$market->deliveries($account,$status);
?>
<div class="we-market-safe-note"><strong>Bóveda de Entregas:</strong> los objetos permanecen protegidos aquí hasta que solicites retirarlos.</div>
<div class="we-market-grid">
<?php foreach($deliveries as $d): ?>
<article class="we-market-card"><div class="we-market-item-icon"><span>📦</span></div><div class="we-market-card-body">
<h3><?= $e((string)($d['ItemName']?:$d['DecodedItem']['name'])) ?></h3>
<div class="we-market-meta"><span>Entrega #<?= (int)$d['DeliveryId'] ?></span><span><?= $e((string)$d['Status']) ?></span></div>
<div class="we-market-seller">Vendedor: <?= $e((string)$d['SellerAccount']) ?></div>
<?php if($d['Status']==='PENDING'): ?><form method="post"><input type="hidden" name="_csrf" value="<?= $e((string)($payload['csrf_token']??'')) ?>"><input type="hidden" name="delivery_id" value="<?= (int)$d['DeliveryId'] ?>"><button name="market_claim" value="1">Retirar al Warehouse</button></form><?php endif; ?>
</div></article>
<?php endforeach; ?>
</div>
<?php endif; ?>
</div>
        <?php
        return [
            'title'=>'Mercado Web',
            'html'=>(string)ob_get_clean()
        ];
    }

    return null;
};
