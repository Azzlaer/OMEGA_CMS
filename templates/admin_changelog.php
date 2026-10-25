<?php
use Omega\Core\Security;
use Omega\Core\Csrf;
$e=[Security::class,'e'];
?>
<div class="admin-toolbar"><a class="btn btn-primary" href="/admin/changelog/new">+ Nueva actualización</a></div>
<div class="admin-panel">
<table class="admin-table">
<thead><tr><th>ID</th><th>Título</th><th>Versión</th><th>Categoría</th><th>Estado</th><th>Destacada</th><th></th></tr></thead>
<tbody>
<?php foreach($rows as $r): ?>
<tr>
<td><?= (int)$r['id'] ?></td>
<td><strong><?= $e($r['title']) ?></strong><small><?= $e($r['slug']) ?></small></td>
<td><?= $e($r['version_label']?:'-') ?></td>
<td><?= $e($r['category']) ?></td>
<td><?= $e($r['status']) ?></td>
<td><?= !empty($r['featured'])?'Sí':'No' ?></td>
<td class="admin-actions-cell">
<a href="/admin/changelog/edit?id=<?= (int)$r['id'] ?>">Editar</a>
<form method="post" action="/admin/changelog/delete" onsubmit="return confirm('¿Eliminar esta actualización?')">
<input type="hidden" name="_csrf" value="<?= $e(Csrf::token()) ?>">
<input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
<button>Eliminar</button>
</form>
</td>
</tr>
<?php endforeach; ?>
<?php if(!$rows): ?><tr><td colspan="7" class="empty-cell">Todavía no hay entradas de changelog.</td></tr><?php endif; ?>
</tbody></table>
</div>
