<?php
declare(strict_types=1);
?><!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>OmegaMU Assets Diagnostic</title>
<link rel="stylesheet" href="/public/assets/css/omega.css?v=171">
<style>
body{padding:30px}
.asset-test{padding:20px;border:1px solid #d5a33d;background:#111820;color:#fff}
.ok{color:#70d990}.bad{color:#ff7e88}
</style>
</head>
<body>
<div class="asset-test">
<h1>OmegaMU Assets Diagnostic v0.17.1</h1>
<p>Si ves esta caja oscura con borde dorado, el CSS está cargando.</p>
<p id="js-status" class="bad">JavaScript: cargando…</p>
</div>
<script src="/public/assets/js/omega.js?v=171"></script>
<script>
document.getElementById('js-status').textContent='JavaScript: OK';
document.getElementById('js-status').className='ok';
</script>
</body>
</html>
