<?php
use Omega\Core\Security; use Omega\Core\Csrf;
$e=[Security::class,'e'];
?>
<div class="admin-toolbar"><a class="btn btn-primary" href="/admin/news/new">+ Nueva noticia</a></div>
<div class="admin-panel">
<table class="admin-table"><thead><tr><th>ID</th><th>Título</th><th>Categoría</th><th>Estado</th><th>Destacada</th><th></th></tr></thead><tbody>
<?php foreach($rows as $r): ?><tr>
<td><?= (int)$r['id'] ?></td><td><strong><?= $e($r['title']) ?></strong><small><?= $e($r['slug']) ?></small></td>
<td><?= $e($r['category']??'') ?></td><td><?= $e($r['status']) ?></td><td><?= !empty($r['featured'])?'Sí':'No' ?></td>
<td class="admin-actions-cell"><a href="/admin/news/edit?id=<?= (int)$r['id'] ?>">Editar</a>
<form method="post" action="/admin/news/delete" onsubmit="return confirm('¿Eliminar noticia?')"><input type="hidden" name="_csrf" value="<?= $e(Csrf::token()) ?>"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button>Eliminar</button></form></td>
</tr><?php endforeach; ?>
<?php if(!$rows): ?><tr><td colspan="6" class="empty-cell">No hay noticias.</td></tr><?php endif; ?>
</tbody></table>
</div>
