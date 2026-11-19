<?php
declare(strict_types=1);

require_once __DIR__.'/WebShopService.php';

return static function(string $hook,array $payload,array $config,array $context): mixed {
    /** @var \Omega\Core\Database $cmsDb */
    $cmsDb=$context['cms_db'];
    /** @var \Omega\Core\Database $muDb */
    $muDb=$context['mu_db'];

    $service=new OmegaWebShopService($cmsDb,$muDb);
    $e=static fn(string $v):string=>htmlspecialchars($v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');

    if($hook==='user.page'){
        $account=(string)($payload['account_id']??'');
        $method=(string)($payload['method']??'GET');
        $query=(array)($payload['query']??[]);
        $post=(array)($payload['post']??[]);
        $csrf=$e((string)($payload['csrf_token']??''));

        $error=null;
        $success=null;

        if($method==='POST'){
            try{
                $action=(string)($post['shop_action']??'');
                if($action==='buy'){
                    $orderId=$service->buy(
                        $account,
                        (int)($post['product_id']??0),
                        max(1,(int)($post['quantity']??1)),
                        (string)($_SERVER['REMOTE_ADDR']??'')
                    );
                    $success='Compra registrada correctamente. Orden #'.$orderId.'.';
                }
            }catch(Throwable $ex){
                $error=$ex->getMessage();
            }
        }

        $category=trim((string)($query['category']??''));
        $search=trim((string)($query['q']??''));
        $settings=$service->settings();
        $products=$service->products($category,$search,true);
        $categories=$service->categories(true);
        $balance=$service->balance($account);
        $orders=$service->orders($account,25);
        $deliveries=$service->deliveries($account,25);

        ob_start(); ?>
<style>
.ws-wrap{display:grid;gap:16px}
.ws-head{display:flex;justify-content:space-between;gap:16px;align-items:center;flex-wrap:wrap}
.ws-balance{padding:10px 14px;border:1px solid #5b4723;border-radius:9px;background:#17130b;color:#f0c96f}
.ws-filters{display:flex;gap:8px;flex-wrap:wrap}
.ws-filters a,.ws-filters input{border:1px solid #344153;background:#0d141b;color:#dce5ec;border-radius:7px;padding:8px 10px;text-decoration:none}
.ws-products{display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:12px}
.ws-card{border:1px solid #2c3944;border-radius:10px;background:#10171e;padding:14px;display:grid;gap:10px}
.ws-card.featured{border-color:#6a5227;box-shadow:inset 0 0 0 1px rgba(213,163,61,.08)}
.ws-card h3{margin:0;color:#f0cf7a;font:18px Georgia,serif}
.ws-card p{margin:0;color:#8797a5;font-size:10px;line-height:1.5}
.ws-card-meta{display:flex;justify-content:space-between;color:#6f8293;font-size:9px}
.ws-price{font-size:19px;color:#f2ca69;font-weight:800}
.ws-buy{display:flex;gap:7px}
.ws-buy input{width:65px;background:#0a1016;color:#fff;border:1px solid #344153;border-radius:6px;padding:8px}
.ws-table{width:100%;border-collapse:collapse;font-size:10px}
.ws-table th,.ws-table td{padding:8px;border-bottom:1px solid #25313a;text-align:left}
.ws-table th{color:#748696;text-transform:uppercase;font-size:8px}
.ws-section{border:1px solid #293640;border-radius:10px;background:#0f161d;padding:14px}
.ws-section h3{margin:0 0 10px;color:#e8c66f;font:18px Georgia,serif}
@media(max-width:650px){.ws-products{grid-template-columns:1fr}.ws-table{display:block;overflow:auto}}
</style>
<div class="ws-wrap">
<div class="ws-head">
<div>
<span class="eyebrow">OMEGA COMMERCE</span>
<h2>🛍️ WebShop PRO</h2>
<p class="muted-copy">Tienda oficial del servidor integrada con tu cuenta MU.</p>
</div>
<?php if(($settings['show_balance']??'1')==='1'): ?>
<div class="ws-balance">Saldo: <strong><?= number_format($balance) ?> <?= $e((string)($settings['credits_name']??'Credits')) ?></strong></div>
<?php endif; ?>
</div>

<?php if($error): ?><div class="form-alert error"><?= $e($error) ?></div><?php endif; ?>
<?php if($success): ?><div class="form-alert success"><?= $e($success) ?></div><?php endif; ?>

<div class="ws-filters">
<a href="/account/modules/webshop-pro">Todo</a>
<?php foreach($categories as $cat): ?>
<a href="/account/modules/webshop-pro?category=<?= rawurlencode((string)$cat['Slug']) ?>"><?= $e((string)($cat['Icon']??'⬡')) ?> <?= $e((string)$cat['Name']) ?></a>
<?php endforeach; ?>
<form method="get" action="/account/modules/webshop-pro">
<input type="text" name="q" value="<?= $e($search) ?>" placeholder="Buscar producto">
</form>
</div>

<div class="ws-products">
<?php foreach($products as $p): ?>
<article class="ws-card <?= !empty($p['IsFeatured'])?'featured':'' ?>">
<div class="ws-card-meta"><span><?= $e((string)($p['CategoryName']??'General')) ?></span><span><?= $e((string)$p['ProductType']) ?></span></div>
<h3><?= $e((string)$p['Name']) ?></h3>
<p><?= $e((string)($p['Description']??'')) ?></p>
<div class="ws-price"><?= number_format((int)$p['PriceCredits']) ?> <?= $e((string)($settings['credits_name']??'Credits')) ?></div>
<?php if($p['Stock']!==null): ?><small class="muted-copy">Stock: <?= (int)$p['Stock'] ?></small><?php endif; ?>
<form method="post" class="ws-buy">
<input type="hidden" name="_csrf" value="<?= $csrf ?>">
<input type="hidden" name="shop_action" value="buy">
<input type="hidden" name="product_id" value="<?= (int)$p['ProductId'] ?>">
<?php if((string)$p['ProductType']==='ITEM'): ?><input type="number" name="quantity" min="1" max="10" value="1"><?php else: ?><input type="hidden" name="quantity" value="1"><?php endif; ?>
<button class="btn btn-primary" type="submit">Comprar</button>
</form>
</article>
<?php endforeach; ?>
<?php if(!$products): ?><div class="notice-box">No hay productos disponibles con esos filtros.</div><?php endif; ?>
</div>

<section class="ws-section">
<h3>Mis órdenes</h3>
<table class="ws-table"><thead><tr><th>#</th><th>Producto</th><th>Total</th><th>Estado</th><th>Fecha</th></tr></thead><tbody>
<?php foreach($orders as $o): ?><tr>
<td><?= (int)$o['OrderId'] ?></td>
<td><?= $e((string)$o['ProductName']) ?></td>
<td><?= number_format((int)$o['TotalPrice']) ?></td>
<td><?= $e((string)$o['Status']) ?></td>
<td><?= $e((string)$o['CreatedAt']) ?></td>
</tr><?php endforeach; ?>
</tbody></table>
</section>

<section class="ws-section">
<h3>Entregas</h3>
<table class="ws-table"><thead><tr><th>#</th><th>Tipo</th><th>Estado</th><th>Creada</th></tr></thead><tbody>
<?php foreach($deliveries as $d): ?><tr>
<td><?= (int)$d['DeliveryId'] ?></td>
<td><?= $e((string)$d['DeliveryType']) ?></td>
<td><?= $e((string)$d['Status']) ?></td>
<td><?= $e((string)$d['CreatedAt']) ?></td>
</tr><?php endforeach; ?>
</tbody></table>
</section>
</div>
<?php
        return ['title'=>'WebShop PRO','html'=>(string)ob_get_clean()];
    }

    if($hook==='admin.action'){
        $action=(string)($payload['action']??'');
        $data=(array)($payload['data']??[]);

        if($action==='save_settings'){
            $service->saveSettings($data);
            return 'Configuración de WebShop PRO guardada.';
        }
        if($action==='save_category'){
            $id=$service->saveCategory($data);
            return 'Categoría guardada (#'.$id.').';
        }
        if($action==='save_product'){
            $id=$service->saveProduct($data);
            return 'Producto guardado (#'.$id.').';
        }
        if($action==='adjust_credits'){
            $account=trim((string)($data['account_id']??''));
            $amount=(int)($data['amount']??0);
            $balance=$service->adjustCredits($account,$amount,(string)($data['note']??'Ajuste AdminCP'),'ADMIN');
            return 'Saldo actualizado. Nuevo saldo de '.$account.': '.$balance.'.';
        }
        if($action==='process_delivery'){
            return $service->processDelivery((int)($data['delivery_id']??0));
        }
        if($action==='runtime_check'){
            $d=$service->diagnostic();
            return 'WebShop runtime activo · categorías '.$d['categories'].' · productos '.$d['products'].' · wallets '.$d['wallets'].' · entregas pendientes '.$d['pending_deliveries'].'.';
        }
        return null;
    }

    if($hook==='admin.panel'){
        $s=$service->settings();
        $stats=$service->adminStats();
        $categories=$service->categories(false);
        $products=$service->products('','',false);
        $orders=$service->recentOrders(30);
        $pending=$service->pendingDeliveries(30);
        $token=$e((string)($payload['csrf_token']??''));
        $checked=static fn(string $k,bool $d=false):string => (($s[$k]??($d?'1':'0'))==='1')?' checked':'';

        ob_start(); ?>
<style>
.wsa-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}.wsa-box{background:#11171e;border:1px solid #293440;border-radius:10px;padding:15px}.wsa-box h3{margin:0 0 12px;color:#efca72;font:19px Georgia,serif}.wsa-form{display:grid;gap:9px}.wsa-form label{font-size:9px;color:#9babb8}.wsa-form input,.wsa-form select,.wsa-form textarea{width:100%;margin-top:4px;background:#0a1016;color:#e7eef4;border:1px solid #344153;border-radius:6px;padding:8px}.wsa-check{display:flex!important;align-items:center;gap:7px}.wsa-check input{width:auto}.wsa-two{display:grid;grid-template-columns:1fr 1fr;gap:8px}.wsa-stats{display:grid;grid-template-columns:repeat(4,1fr);gap:8px}.wsa-stat{padding:12px;border:1px solid #2c3843;border-radius:8px;background:#0a1118}.wsa-stat span{display:block;color:#738495;font-size:8px}.wsa-stat strong{display:block;color:#ebca76;font-size:20px;margin-top:4px}.wsa-table{width:100%;border-collapse:collapse;font-size:9px}.wsa-table td,.wsa-table th{padding:7px;border-bottom:1px solid #27323b;text-align:left}.wsa-table th{color:#718392}@media(max-width:950px){.wsa-grid{grid-template-columns:1fr}.wsa-stats{grid-template-columns:1fr 1fr}}
</style>
<div class="wsa-stats">
<div class="wsa-stat"><span>PRODUCTOS</span><strong><?= number_format($stats['products']) ?></strong></div>
<div class="wsa-stat"><span>ÓRDENES</span><strong><?= number_format($stats['orders']) ?></strong></div>
<div class="wsa-stat"><span>ENTREGAS PENDIENTES</span><strong><?= number_format($stats['pending']) ?></strong></div>
<div class="wsa-stat"><span>CRÉDITOS EN WALLETS</span><strong><?= number_format($stats['credits']) ?></strong></div>
</div>

<div class="wsa-grid" style="margin-top:14px">
<section class="wsa-box">
<h3>⚙ Configuración</h3>
<form method="post" action="/admin/modules/action" class="wsa-form">
<input type="hidden" name="_csrf" value="<?= $token ?>"><input type="hidden" name="slug" value="webshop-pro"><input type="hidden" name="module_action" value="save_settings">
<label class="wsa-check"><input type="checkbox" name="enabled" value="1"<?= $checked('enabled') ?>> Tienda activa</label>
<label class="wsa-check"><input type="checkbox" name="require_offline" value="1"<?= $checked('require_offline',true) ?>> Exigir cuenta offline</label>
<label class="wsa-check"><input type="checkbox" name="show_balance" value="1"<?= $checked('show_balance',true) ?>> Mostrar saldo</label>
<label class="wsa-check"><input type="checkbox" name="allow_item_orders" value="1"<?= $checked('allow_item_orders') ?>> Permitir compras ITEM</label>
<label class="wsa-check"><input type="checkbox" name="allow_service_orders" value="1"<?= $checked('allow_service_orders',true) ?>> Permitir servicios</label>
<div class="wsa-two"><label>Nombre moneda<input name="credits_name" value="<?= $e((string)$s['credits_name']) ?>"></label><label>Cantidad máxima<input type="number" name="max_quantity" min="1" max="100" value="<?= (int)$s['max_quantity'] ?>"></label></div>
<label>Mensaje mantenimiento<textarea name="maintenance_message"><?= $e((string)$s['maintenance_message']) ?></textarea></label>
<button class="btn btn-primary">Guardar</button>
</form>
</section>

<section class="wsa-box">
<h3>💰 Ajustar créditos</h3>
<form method="post" action="/admin/modules/action" class="wsa-form">
<input type="hidden" name="_csrf" value="<?= $token ?>"><input type="hidden" name="slug" value="webshop-pro"><input type="hidden" name="module_action" value="adjust_credits">
<label>Cuenta MU<input name="account_id" maxlength="10" required></label>
<label>Ajuste (+/-)<input type="number" name="amount" required></label>
<label>Motivo<input name="note" maxlength="500" value="Ajuste manual AdminCP"></label>
<button class="btn btn-primary">Aplicar ajuste</button>
</form>
</section>

<section class="wsa-box">
<h3>📁 Nueva categoría</h3>
<form method="post" action="/admin/modules/action" class="wsa-form">
<input type="hidden" name="_csrf" value="<?= $token ?>"><input type="hidden" name="slug" value="webshop-pro"><input type="hidden" name="module_action" value="save_category">
<div class="wsa-two"><label>Nombre<input name="Name" required></label><label>Slug<input name="Slug"></label></div>
<div class="wsa-two"><label>Icono<input name="Icon" value="⬡"></label><label>Orden<input type="number" name="SortOrder" value="100"></label></div>
<label>Descripción<input name="Description"></label>
<label class="wsa-check"><input type="checkbox" name="IsActive" value="1" checked> Activa</label>
<button class="btn btn-primary">Guardar categoría</button>
</form>
</section>

<section class="wsa-box">
<h3>🛍 Nuevo producto</h3>
<form method="post" action="/admin/modules/action" class="wsa-form">
<input type="hidden" name="_csrf" value="<?= $token ?>"><input type="hidden" name="slug" value="webshop-pro"><input type="hidden" name="module_action" value="save_product">
<div class="wsa-two"><label>Nombre<input name="Name" required></label><label>Slug<input name="Slug"></label></div>
<div class="wsa-two"><label>Categoría<select name="CategoryId"><option value="">General</option><?php foreach($categories as $c): ?><option value="<?= (int)$c['CategoryId'] ?>"><?= $e((string)$c['Name']) ?></option><?php endforeach; ?></select></label>
<label>Tipo<select name="ProductType"><option>ITEM</option><option>VIP</option><option>RESET</option><option>ZEN</option><option>UNBAN</option></select></label></div>
<div class="wsa-two"><label>Precio Credits<input type="number" name="PriceCredits" min="0" value="100"></label><label>Stock (vacío=ilimitado)<input type="number" name="Stock" min="0"></label></div>
<label>Descripción<textarea name="Description"></textarea></label>
<label>Imagen URL<input name="ImageUrl"></label>
<div class="wsa-two"><label>Item HEX (32 chars)<input name="ItemHex"></label><label>VIP días<input type="number" name="VipDays" value="30"></label></div>
<div class="wsa-two"><label>VIP level<input type="number" name="VipLevel" value="1"></label><label>Zen amount<input type="number" name="ZenAmount" value="1000000"></label></div>
<label class="wsa-check"><input type="checkbox" name="IsActive" value="1" checked> Activo</label>
<label class="wsa-check"><input type="checkbox" name="IsFeatured" value="1"> Destacado</label>
<label class="wsa-check"><input type="checkbox" name="RequiresOffline" value="1" checked> Exigir offline</label>
<button class="btn btn-primary">Crear producto</button>
</form>
</section>
</div>

<section class="wsa-box" style="margin-top:14px">
<h3>Catálogo actual</h3>
<table class="wsa-table"><thead><tr><th>ID</th><th>Producto</th><th>Tipo</th><th>Precio</th><th>Stock</th><th>Activo</th></tr></thead><tbody>
<?php foreach($products as $p): ?><tr><td><?= (int)$p['ProductId'] ?></td><td><?= $e((string)$p['Name']) ?></td><td><?= $e((string)$p['ProductType']) ?></td><td><?= number_format((int)$p['PriceCredits']) ?></td><td><?= $p['Stock']===null?'∞':(int)$p['Stock'] ?></td><td><?= (int)$p['IsActive']?'Sí':'No' ?></td></tr><?php endforeach; ?>
</tbody></table>
</section>

<section class="wsa-box" style="margin-top:14px">
<h3>Órdenes recientes</h3>
<table class="wsa-table"><thead><tr><th>#</th><th>Cuenta</th><th>Producto</th><th>Total</th><th>Estado</th></tr></thead><tbody>
<?php foreach($orders as $o): ?><tr><td><?= (int)$o['OrderId'] ?></td><td><?= $e((string)$o['AccountId']) ?></td><td><?= $e((string)$o['ProductName']) ?></td><td><?= number_format((int)$o['TotalPrice']) ?></td><td><?= $e((string)$o['Status']) ?></td></tr><?php endforeach; ?>
</tbody></table>
</section>

<section class="wsa-box" style="margin-top:14px">
<h3>Entregas pendientes</h3>
<table class="wsa-table"><thead><tr><th>#</th><th>Orden</th><th>Cuenta</th><th>Tipo</th><th>Estado</th><th>Acción</th></tr></thead><tbody>
<?php foreach($pending as $d): ?><tr><td><?= (int)$d['DeliveryId'] ?></td><td><?= (int)$d['OrderId'] ?></td><td><?= $e((string)$d['AccountId']) ?></td><td><?= $e((string)$d['DeliveryType']) ?></td><td><?= $e((string)$d['Status']) ?></td><td>
<?php if(in_array((string)$d['DeliveryType'],['VIP','ZEN','UNBAN'],true)): ?>
<form method="post" action="/admin/modules/action">
<input type="hidden" name="_csrf" value="<?= $token ?>"><input type="hidden" name="slug" value="webshop-pro"><input type="hidden" name="module_action" value="process_delivery"><input type="hidden" name="delivery_id" value="<?= (int)$d['DeliveryId'] ?>">
<button class="btn btn-primary" type="submit">Procesar</button>
</form>
<?php else: ?><span class="muted-text">Pendiente de integrador</span><?php endif; ?>
</td></tr><?php endforeach; ?>
</tbody></table>
</section>
<?php
        return (string)ob_get_clean();
    }

    return null;
};
