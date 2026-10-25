<?php
declare(strict_types=1);
require __DIR__ . '/config.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name(SESSION_NAME);
    session_start();
}
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function fail(string $msg, int $code=400): never {
    http_response_code($code);
    echo json_encode(['ok'=>false,'error'=>$msg], JSON_UNESCAPED_UNICODE);
    exit;
}
if (empty($_SESSION['auth'])) fail('No autenticado.', 401);

$job = (string)($_GET['job'] ?? '');
if (!preg_match('/^[a-f0-9]{32}$/', $job)) fail('Job invalido.');

$statusFile = JOB_DIR.DIRECTORY_SEPARATOR.$job.'.status.json';
$resultFile = JOB_DIR.DIRECTORY_SEPARATOR.$job.'.result.json';
$logFile = JOB_DIR.DIRECTORY_SEPARATOR.$job.'.log.txt';
$startupLogFile = JOB_DIR.DIRECTORY_SEPARATOR.$job.'.startup.log.txt';

if (!is_file($statusFile)) fail('Trabajo no encontrado.', 404);

$status = json_decode((string)@file_get_contents($statusFile), true);
$result = is_file($resultFile) ? json_decode((string)@file_get_contents($resultFile), true) : null;
$log = is_file($logFile) ? (string)@file_get_contents($logFile) : '';
if (is_file($startupLogFile)) {
    $startup = trim((string)@file_get_contents($startupLogFile));
    if ($startup !== '') $log = "=== POWERSHELL STARTUP ===\n".$startup."\n\n".$log;
}
if (strlen($log) > 140000) $log = substr($log, -140000);

if (is_array($status) && (($status['state'] ?? '') === 'queued')) {
    $age = time() - (int)@filemtime($statusFile);
    if ($age >= 12) {
        $status['state'] = 'error';
        $status['progress'] = 100;
        $status['step'] = 'PowerShell no inicio el trabajo. Revisa Log tecnico, permisos y popen().';
        $status['updated'] = date(DATE_ATOM);
    }
}

echo json_encode([
    'ok'=>true,
    'job'=>$job,
    'status'=>is_array($status)?$status:[],
    'result'=>is_array($result)?$result:null,
    'log'=>$log
], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
