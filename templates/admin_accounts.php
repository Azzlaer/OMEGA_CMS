<?php
use Omega\Core\Security; use Omega\Core\Csrf; $e=[Security::class,'e'];
?>
<div class="admin-panel">
<form method="get" action="/admin/accounts" class="admin-search"><input name="q" value="<?= $e($q??'') ?>" placeholder="Cuenta o email"><button class="btn btn-primary">Buscar</button></form>
<table class="admin-table"><thead><tr><th>Cuenta</th><th>Email</th><th>VIP</th><th>Online</th><th>Bloqueo</th><th>Horas</th><th></th></tr></thead><tbody>
<?php foreach($rows as $r): ?><tr>
<td><strong><?= $e($r['memb___id']) ?></strong></td><td><?= $e($r['mail_addr']??'') ?></td><td><?= (int)$r['AccountLevel'] ?></td>
<td><?= (int)$r['ConnectStat']===1?'ONLINE':'Offline' ?></td><td><?= (string)$r['bloc_code']==='1'?'Bloqueada':'Normal' ?></td><td><?= (int)$r['OnlineHours'] ?></td>
<td class="admin-actions-cell"><form method="post" action="/admin/accounts/block"><input type="hidden" name="_csrf" value="<?= $e(Csrf::token()) ?>"><input type="hidden" name="account_id" value="<?= $e($r['memb___id']) ?>"><input type="hidden" name="blocked" value="<?= (string)$r['bloc_code']==='1'?'0':'1' ?>"><button><?= (string)$r['bloc_code']==='1'?'Desbloquear':'Bloquear' ?></button></form></td>
</tr><?php endforeach; ?></tbody></table>
</div>
