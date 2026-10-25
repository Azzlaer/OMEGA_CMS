<?php
use Omega\Core\Security;
use Omega\Core\Csrf;
$e=[Security::class,'e'];
$rows=$rows??[];
?>
<div class="admin-toolbar">
    <a class="btn btn-primary" href="/admin/guides/new">+ Nueva guía</a>
    <a class="btn btn-ghost" href="/guides" target="_blank" rel="noopener">↗ Ver sección pública</a>
</div>
<div class="admin-panel">
<table class="admin-table"><thead><tr><th>Logo</th><th>Guía</th><th>Autor</th><th>Estado</th><th>Vistas</th><th>Fecha</th><th></th></tr></thead><tbody>
<?php foreach($rows as $r): $logo=trim((string)($r['logo_url']??'')); ?>
<tr>
<td><div class="admin-guide-thumb"><?php if($logo!==''): ?><img src="<?= $e($logo) ?>" alt=""><?php else: ?><span>Ω</span><?php endif; ?></div></td>
<td><strong><?= $e((string)$r['title']) ?></strong><small><?= $e((string)($r['category']?:'General')) ?> · /guides/<?= $e((string)$r['slug']) ?><?= !empty($r['featured'])?' · ★ Destacada':'' ?></small></td>
<td><?= $e((string)$r['author']) ?></td>
<td><span class="guide-admin-status <?= $e((string)$r['status']) ?>"><?= $e((string)$r['status']) ?></span></td>
<td><?= number_format((int)($r['views']??0)) ?></td>
<td><?= $e(date('d/m/Y',strtotime((string)($r['published_at']?:$r['created_at'])))) ?></td>
<td class="admin-actions-cell"><a href="/admin/guides/edit?id=<?= (int)$r['id'] ?>">Editar</a>
<form method="post" action="/admin/guides/delete" onsubmit="return confirm('¿Eliminar esta guía definitivamente?')"><input type="hidden" name="_csrf" value="<?= $e(Csrf::token()) ?>"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button>Eliminar</button></form></td>
</tr>
<?php endforeach; ?>
<?php if(!$rows): ?><tr><td colspan="7" class="empty-cell">No hay guías creadas todavía.</td></tr><?php endif; ?>
</tbody></table>
</div>
