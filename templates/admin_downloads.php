<?php
use Omega\Core\Security; use Omega\Core\Csrf; $e=[Security::class,'e'];
?>
<div class="admin-toolbar"><a class="btn btn-primary" href="/admin/downloads/new">+ Nueva descarga</a></div>
<div class="admin-panel">
<table class="admin-table"><thead><tr><th>ID</th><th>Título</th><th>Categoría</th><th>Estado</th><th>Orden</th><th></th></tr></thead><tbody>
<?php foreach($rows as $r): ?><tr>
<td><?= (int)$r['id'] ?></td><td><strong><?= $e($r['title']) ?></strong><small><?= $e($r['url']) ?></small></td>
<td><?= $e($r['category']??'') ?></td><td><?= !empty($r['is_active'])?'Activa':'Oculta' ?></td><td><?= (int)$r['sort_order'] ?></td>
<td class="admin-actions-cell"><a href="/admin/downloads/edit?id=<?= (int)$r['id'] ?>">Editar</a>
<form method="post" action="/admin/downloads/delete" onsubmit="return confirm('¿Eliminar descarga?')"><input type="hidden" name="_csrf" value="<?= $e(Csrf::token()) ?>"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button>Eliminar</button></form></td>
</tr><?php endforeach; ?>
<?php if(!$rows): ?><tr><td colspan="6" class="empty-cell">No hay descargas.</td></tr><?php endif; ?>
</tbody></table></div>
