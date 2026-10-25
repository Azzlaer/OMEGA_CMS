<?php
declare(strict_types=1);
require __DIR__ . '/config.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name(SESSION_NAME);
    session_start();
}
if (empty($_SESSION['auth'])) {
    http_response_code(401);
    exit('No autenticado');
}

$host = trim((string)($_GET['host'] ?? DEFAULT_RDP_HOST));
$port = (int)($_GET['port'] ?? 3389);
$user = trim((string)($_GET['user'] ?? ''));

if ($host === '' || !preg_match('/^[A-Za-z0-9.\-:\[\]]+$/', $host)) {
    http_response_code(400);
    exit('Host invalido');
}
if ($port < 1 || $port > 65535) {
    http_response_code(400);
    exit('Puerto invalido');
}
if ($user !== '' && !preg_match('/^[A-Za-z0-9._ @\\\-]+$/', $user)) {
    http_response_code(400);
    exit('Usuario invalido');
}

$full = $host . ($port === 3389 ? '' : ':' . $port);

$lines = [
    'screen mode id:i:2',
    'use multimon:i:0',
    'desktopwidth:i:1920',
    'desktopheight:i:1080',
    'session bpp:i:32',
    'full address:s:' . $full,
    'prompt for credentials:i:1',
    'authentication level:i:2',
    'enablecredsspsupport:i:1',
    'redirectclipboard:i:1',
    'redirectprinters:i:0',
    'redirectcomports:i:0',
    'redirectsmartcards:i:0',
    'audiomode:i:0'
];
if ($user !== '') $lines[] = 'username:s:' . $user;

$content = implode("\r\n", $lines) . "\r\n";

header('Content-Type: application/x-rdp');
header('Content-Disposition: attachment; filename="RDP_Guard_Connection.rdp"');
header('Content-Length: '.strlen($content));
echo $content;
