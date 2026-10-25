<?php
declare(strict_types=1);

$scriptDir=str_replace('\\','/',dirname((string)($_SERVER['SCRIPT_NAME']??'/index.php')));
if($scriptDir==='/' || $scriptDir==='.' || $scriptDir==='\\') $scriptDir='';
$url=$scriptDir.'/assets/js/bbcode-editor.js?v=163';
?><!doctype html>
<html lang="es">
<head><meta charset="utf-8"><title>BBCode Diagnostic</title>
<style>
body{background:#10161d;color:#dce3ea;font:15px Segoe UI;padding:30px}
.ok{color:#6ecb8b}.bad{color:#ff8282}
button,textarea{font:inherit}
textarea{display:block;width:90%;height:160px;margin:15px 0;background:#081018;color:white}
</style>
</head>
<body>
<h1>OmegaMU BBCode Diagnostic v0.16.3</h1>
<p>Script público calculado: <code><?=htmlspecialchars($url)?></code></p>
<div data-bbcode-root>
<button type="button" data-bbtag="b">B</button>
<button type="button" data-bbtag="center">CENTRO</button>
<div data-bbcode-status class="bbcode-js-status bad">Editor BBCode cargando…</div>
<textarea data-bbcode-editor>Prueba</textarea>
</div>
<script src="<?=htmlspecialchars($url)?>" defer></script>
</body>
</html>
