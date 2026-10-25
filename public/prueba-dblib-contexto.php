<?php
declare(strict_types=1);
header('Content-Type: text/html; charset=UTF-8');

function h($v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8');
}

$result = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $host = trim((string)($_POST['host'] ?? '152.230.104.97'));
    $port = (int)($_POST['port'] ?? 1433);
    $db   = trim((string)($_POST['db'] ?? 'MuOnline97'));
    $user = trim((string)($_POST['user'] ?? 'muserver'));
    $pass = (string)($_POST['pass'] ?? '');

    $attempts = [
        'BASIC' => "dblib:host={$host}:{$port};dbname={$db}",
        'UTF-8' => "dblib:host={$host}:{$port};dbname={$db};charset=UTF-8",
    ];
    $rows = [];

    foreach ($attempts as $mode => $dsn) {
        $start = microtime(true);
        try {
            $pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_TIMEOUT => 8,
                PDO::ATTR_PERSISTENT => false,
            ]);
            $probe = $pdo->query("
                SELECT CAST(SERVERPROPERTY('ProductVersion') AS VARCHAR(128)) ProductVersion,
                       DB_NAME() CurrentDatabase,
                       SUSER_SNAME() LoginName
            ")->fetch();
            $rows[] = [
                'mode' => $mode, 'ok' => true,
                'time' => round((microtime(true)-$start)*1000,1),
                'msg' => "SQL {$probe['ProductVersion']} · DB {$probe['CurrentDatabase']} · Login {$probe['LoginName']}"
            ];
        } catch (Throwable $e) {
            $rows[] = [
                'mode' => $mode, 'ok' => false,
                'time' => round((microtime(true)-$start)*1000,1),
                'msg' => $e->getMessage()
            ];
        }
    }
    $result = $rows;
}
?><!doctype html>
<html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Omega v0.2.8 DBLIB Context</title>
<style>
body{background:#0b1017;color:#eef4fb;font:15px/1.5 Segoe UI,Arial,sans-serif;margin:0}
main{max-width:900px;margin:32px auto;padding:20px}.card{background:#151c25;border:1px solid #2b3a4e;border-radius:14px;padding:22px;margin-bottom:18px}
input{width:100%;box-sizing:border-box;background:#080d13;color:#fff;border:1px solid #394a61;border-radius:8px;padding:11px;margin:5px 0 13px}
button{padding:11px 18px;background:#c69739;border:0;border-radius:8px;font-weight:700}.ok{border-left:4px solid #31d98b}.bad{border-left:4px solid #ff6470}
.result{background:#0c121a;border:1px solid #2b394c;border-radius:10px;padding:14px;margin-top:10px}
code{word-break:break-all;color:#efcf83}.muted{color:#9bb0c8}
</style></head><body><main>
<div class="card"><h1>Omega v0.2.8 · DBLIB mismo contexto</h1>
<p>Este archivo está dentro de <code>/public/</code>, igual que el instalador que estás ejecutando.</p>
<p class="muted">Archivo: <?=h(__FILE__)?><br>DocumentRoot: <?=h($_SERVER['DOCUMENT_ROOT'] ?? '')?><br>
PHP: <?=h(PHP_VERSION)?> · <?=h(PHP_SAPI)?> · pdo_dblib: <?=extension_loaded('pdo_dblib')?'SI':'NO'?></p>
</div>
<div class="card">
<form method="post">
<label>Host<input name="host" value="<?=h($_POST['host']??'152.230.104.97')?>"></label>
<label>Puerto<input name="port" value="<?=h($_POST['port']??'1433')?>"></label>
<label>Base<input name="db" value="<?=h($_POST['db']??'MuOnline97')?>"></label>
<label>Usuario<input name="user" value="<?=h($_POST['user']??'muserver')?>"></label>
<label>Contraseña<input type="password" name="pass" required></label>
<button>Probar BASIC y UTF-8</button>
</form>
<?php if($result): foreach($result as $r): ?>
<div class="result <?=$r['ok']?'ok':'bad'?>">
<strong><?=h($r['mode'])?> — <?=$r['ok']?'FUNCIONA':'FALLÓ'?></strong><br>
<?=h($r['msg'])?><br><span class="muted"><?=h($r['time'])?> ms</span>
</div>
<?php endforeach; endif; ?>
</div>
</main></body></html>