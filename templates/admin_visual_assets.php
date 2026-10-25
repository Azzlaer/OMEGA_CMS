<?php
use Omega\Core\Security;
use Omega\Core\Csrf;
$e=[Security::class,'e'];
$error=$error??null; $success=$success??null;
?>
<?php if($error): ?><div class="form-alert error wide"><?= $e($error) ?></div><?php endif; ?>
<?php if($success): ?><div class="form-alert success wide"><?= $e($success) ?></div><?php endif; ?>

<div class="admin-panel">
<div class="admin-panel-head">
    <div><span class="eyebrow">VISUALES</span><h2>Iconos & Variables</h2></div>
    <span class="admin-ok">DINÁMICO</span>
</div>
<p class="module-help">
Asocia cada variable del CMS a una imagen. Rankings y Usuarios Online utilizan estas asociaciones automáticamente.
</p>
</div>

<?php $currentGroup=null; foreach($rows as $row):
if($currentGroup!==$row['asset_group']):
    if($currentGroup!==null) echo '</div>';
    $currentGroup=$row['asset_group'];
?>
<div class="visual-group-title"><span><?= $e($currentGroup) ?></span></div>
<div class="visual-asset-grid">
<?php endif; ?>

<article class="visual-asset-card">
    <div class="visual-preview">
    <?php if(!empty($row['image_url'])): ?>
        <img src="<?= $e($row['image_url']) ?>" alt="<?= $e($row['label']) ?>">
    <?php else: ?>
        <span>?</span>
    <?php endif; ?>
    </div>

    <div class="visual-info">
        <h3><?= $e($row['label']) ?></h3>
        <code><?= $e($row['asset_key']) ?></code>
        <small><?= $e($row['image_url'] ?: 'Sin imagen asignada') ?></small>
    </div>

    <form method="post" action="/admin/visual-assets/upload" enctype="multipart/form-data" class="visual-upload">
        <input type="hidden" name="_csrf" value="<?= $e(Csrf::token()) ?>">
        <input type="hidden" name="asset_key" value="<?= $e($row['asset_key']) ?>">
        <input type="file" name="image" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" required>
        <button class="btn btn-primary" type="submit">Subir imagen</button>
    </form>

    <form method="post" action="/admin/visual-assets/url" class="visual-url-form">
        <input type="hidden" name="_csrf" value="<?= $e(Csrf::token()) ?>">
        <input type="hidden" name="asset_key" value="<?= $e($row['asset_key']) ?>">
        <input name="image_url" value="<?= $e($row['image_url']??'') ?>" placeholder="/public/... o https://...">
        <button class="btn btn-ghost" type="submit">Guardar URL</button>
    </form>

    <form method="post" action="/admin/visual-assets/reset">
        <input type="hidden" name="_csrf" value="<?= $e(Csrf::token()) ?>">
        <input type="hidden" name="asset_key" value="<?= $e($row['asset_key']) ?>">
        <button class="visual-reset" type="submit">Restaurar valor predeterminado</button>
    </form>
</article>
<?php endforeach; if($currentGroup!==null) echo '</div>'; ?>
